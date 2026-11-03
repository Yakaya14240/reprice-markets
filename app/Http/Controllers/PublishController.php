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

        return response()->json(['pending' => $items]);
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
            'marketplace_ids' => ['required', 'array', 'min:1'],
            'marketplace_ids.*' => ['integer', 'exists:marketplaces,id'],
        ]);

        $lock = Cache::get("publish_lock_user_{$request->user()->id}");
        if (($lock['status'] ?? null) === 'running') {
            return response()->json(['message' => 'Une publication est déjà en cours.'], 409);
        }

        $count = PendingPriceChange::query()
            ->where('user_id', $request->user()->id)
            ->where('feed_file_id', $feed->id)
            ->count();

        if ($count === 0) {
            return response()->json(['message' => 'Aucune modification en attente.'], 422);
        }

        Cache::put("publish_lock_user_{$request->user()->id}", [
            'status' => 'running',
            'progress' => 1,
            'label' => 'Démarrage…',
            'feed_file_id' => $feed->id,
        ], now()->addHour());

        // Sync for reliability without requiring a worker in local/dev; still reports progress via cache.
        PublishPendingChangesJob::dispatchSync(
            $request->user()->id,
            $feed->id,
            array_map('intval', $data['marketplace_ids']),
        );

        return response()->json([
            'ok' => true,
            'status' => Cache::get("publish_lock_user_{$request->user()->id}"),
        ]);
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
