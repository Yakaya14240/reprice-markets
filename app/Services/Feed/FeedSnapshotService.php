<?php

namespace App\Services\Feed;

use App\Enums\FeedSnapshotStatus;
use App\Models\FeedFile;
use App\Models\FeedSnapshot;
use Illuminate\Support\Facades\Storage;

final class FeedSnapshotService
{
    public function __construct(private readonly FeedIO $feedIO) {}

    public function ensureSnapshot(FeedFile $feed): FeedSnapshot
    {
        return FeedSnapshot::query()->firstOrCreate(
            ['feed_file_id' => $feed->id],
            ['status' => FeedSnapshotStatus::Idle],
        );
    }

    public function refresh(FeedFile $feed): FeedSnapshot
    {
        // Large Lengow feeds can exceed default memory while building the snapshot.
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '1024M');
            @ini_set('max_execution_time', '300');
        }

        $snapshot = $this->ensureSnapshot($feed);
        $snapshot->update([
            'status' => FeedSnapshotStatus::Loading,
            'progress' => 5,
            'progress_label' => 'Connexion à la source…',
            'error_message' => null,
        ]);

        try {
            $snapshot->update([
                'progress' => 35,
                'progress_label' => 'Téléchargement du flux…',
            ]);

            $parsed = $this->feedIO->read($feed);

            $snapshot->update([
                'progress' => 70,
                'progress_label' => 'Indexation des produits…',
            ]);

            $path = "feeds/snapshots/feed_{$feed->id}.json";
            Storage::disk('local')->put($path, json_encode([
                'headers' => $parsed['headers'],
                'products' => $parsed['products'],
            ], JSON_UNESCAPED_UNICODE));

            $snapshot->update([
                'status' => FeedSnapshotStatus::Ready,
                'products_count' => count($parsed['products']),
                'storage_path' => $path,
                'headers' => $parsed['headers'],
                'progress' => 100,
                'progress_label' => 'Flux à jour',
                'loaded_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $snapshot->update([
                'status' => FeedSnapshotStatus::Failed,
                'progress' => 100,
                'progress_label' => 'Échec du chargement',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $snapshot->fresh();
    }

    /**
     * @return array{headers: list<string>, products: list<array<string, string|null>>}
     */
    public function loadProducts(FeedFile $feed): array
    {
        $snapshot = $this->ensureSnapshot($feed);
        if (! $snapshot->storage_path || ! Storage::disk('local')->exists($snapshot->storage_path)) {
            return ['headers' => [], 'products' => []];
        }

        $data = json_decode(Storage::disk('local')->get($snapshot->storage_path), true);

        return [
            'headers' => $data['headers'] ?? [],
            'products' => $data['products'] ?? [],
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string|null>>  $products
     */
    public function persistProducts(FeedFile $feed, array $headers, array $products): void
    {
        $snapshot = $this->ensureSnapshot($feed);
        $path = $snapshot->storage_path ?: "feeds/snapshots/feed_{$feed->id}.json";

        Storage::disk('local')->put($path, json_encode([
            'headers' => $headers,
            'products' => $products,
        ], JSON_UNESCAPED_UNICODE));

        $snapshot->update([
            'storage_path' => $path,
            'headers' => $headers,
            'products_count' => count($products),
            'status' => FeedSnapshotStatus::Ready,
            'loaded_at' => now(),
        ]);
    }
}
