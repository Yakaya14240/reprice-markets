<?php

namespace App\Http\Controllers;

use App\Jobs\PublishPendingChangesJob;
use App\Models\FeedFile;
use App\Models\PendingPriceChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublishController extends Controller
{
    public function pending(Request $request, FeedFile $feed): JsonResponse
    {
        $items = PendingPriceChange::query()
            ->where('user_id', $request->user()->id)
            ->where('feed_file_id', $feed->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (PendingPriceChange $c) => [
                'id' => $c->id,
                'sku' => $c->sku,
                'csv_column' => $c->csv_column,
                'old_price' => $c->old_price,
                'new_price' => $c->new_price,
            ]);

        return response()->json([
            'pending' => $items,
            'write_destination' => $feed->writeDestinationLabel(),
            'can_write' => $feed->hasWritableDestination(),
        ]);
    }

    public function destroyPending(Request $request, FeedFile $feed, PendingPriceChange $pending): JsonResponse
    {
        abort_unless($pending->user_id === $request->user()->id && $pending->feed_file_id === $feed->id, 403);
        $pending->delete();

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, FeedFile $feed): JsonResponse
    {
        $data = $request->validate([
            'marketplace_ids' => ['nullable', 'array'],
            'marketplace_ids.*' => ['integer', 'exists:marketplaces,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $marketplaceIds = array_values(array_map('intval', $data['marketplace_ids'] ?? []));
        $note = filled($data['note'] ?? null) ? trim((string) $data['note']) : null;

        if (! $feed->hasWritableDestination()) {
            return response()->json([
                'message' => 'Destination d’écriture non configurée. Pour un flux URL, renseignez type + chemin d’écriture (local ou SFTP) en admin.',
            ], 422);
        }

        $lockKey = "publish_lock_user_{$request->user()->id}";
        $lock = Cache::get($lockKey);

        // A fatal OOM can leave status=running forever; treat old locks as stale.
        if (($lock['status'] ?? null) === 'running') {
            $startedAt = isset($lock['started_at']) ? strtotime((string) $lock['started_at']) : false;
            $ageSeconds = $startedAt ? (time() - $startedAt) : PHP_INT_MAX;
            if ($ageSeconds < 10 * 60) {
                return response()->json([
                    'message' => 'Une publication est déjà en cours.',
                    'status' => $lock,
                ], 409);
            }

            Cache::put($lockKey, [
                'status' => 'failed',
                'progress' => 100,
                'label' => 'Publication précédente interrompue (verrou expiré). Vous pouvez relancer.',
                'feed_file_id' => $lock['feed_file_id'] ?? $feed->id,
            ], now()->addMinutes(10));
        }

        $count = PendingPriceChange::query()
            ->where('user_id', $request->user()->id)
            ->where('feed_file_id', $feed->id)
            ->count();

        if ($count === 0) {
            return response()->json(['message' => 'Aucune modification en attente.'], 422);
        }

        Cache::put($lockKey, [
            'status' => 'running',
            'progress' => 1,
            'label' => 'Démarrage sync flux…',
            'feed_file_id' => $feed->id,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));

        try {
            PublishPendingChangesJob::dispatchSync(
                $request->user()->id,
                $feed->id,
                $marketplaceIds,
                $note,
            );
        } catch (\Throwable $e) {
            $failed = Cache::get($lockKey);
            if (($failed['status'] ?? null) === 'running') {
                Cache::put($lockKey, [
                    'status' => 'failed',
                    'progress' => 100,
                    'label' => $e->getMessage(),
                    'feed_file_id' => $feed->id,
                ], now()->addMinutes(10));
            }

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'status' => Cache::get($lockKey),
            ], 422);
        }

        $status = Cache::get($lockKey);

        return response()->json([
            'ok' => ($status['status'] ?? null) !== 'failed',
            'status' => $status,
            'message' => $status['label'] ?? null,
        ], ($status['status'] ?? null) === 'failed' ? 422 : 200);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json(
            Cache::get("publish_lock_user_{$request->user()->id}", [
                'status' => 'idle',
                'progress' => 0,
                'label' => null,
            ])
        );
    }
}
