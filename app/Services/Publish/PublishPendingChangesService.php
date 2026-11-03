<?php

namespace App\Services\Publish;

use App\Domain\Offer\Offer;
use App\Domain\Pricing\ZeroPriceGuard;
use App\Enums\PushBatchStatus;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PendingPriceChange;
use App\Models\PriceChangeLog;
use App\Models\PricePushBatch;
use App\Models\User;
use App\Services\Feed\FeedIO;
use App\Services\Feed\FeedSnapshotService;
use App\Services\Feed\LengowCsvParser;
use App\Services\Marketplace\MarketplaceClientFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PublishPendingChangesService
{
    public function __construct(
        private readonly FeedSnapshotService $snapshots,
        private readonly FeedIO $feedIO,
        private readonly LengowCsvParser $parser,
        private readonly MarketplaceClientFactory $marketplaceFactory,
        private readonly ZeroPriceGuard $zeroPriceGuard,
    ) {}

    /**
     * @param  list<int>  $marketplaceIds
     */
    public function publish(User $user, FeedFile $feed, array $marketplaceIds): PricePushBatch
    {
        $lockKey = "publish_lock_user_{$user->id}";
        Cache::put($lockKey, [
            'status' => 'running',
            'progress' => 5,
            'label' => 'Préparation…',
            'feed_file_id' => $feed->id,
        ], now()->addHour());

        $batch = PricePushBatch::query()->create([
            'user_id' => $user->id,
            'marketplace_id' => $marketplaceIds[0] ?? Marketplace::query()->value('id'),
            'feed_file_id' => $feed->id,
            'status' => PushBatchStatus::Running,
            'offers_count' => 0,
            'payload' => ['marketplace_ids' => $marketplaceIds],
            'started_at' => now(),
        ]);

        try {
            $pending = PendingPriceChange::query()
                ->where('user_id', $user->id)
                ->where('feed_file_id', $feed->id)
                ->get();

            if ($pending->isEmpty()) {
                throw new \RuntimeException('Aucune modification en attente.');
            }

            $this->updateProgress($user->id, 15, 'Application des modifications au flux…');

            $data = $this->snapshots->loadProducts($feed);
            $headers = $data['headers'];
            $products = $data['products'];
            $skuColumn = $feed->sku_column;

            $bySku = [];
            foreach ($products as $index => $product) {
                $sku = (string) ($product[$skuColumn] ?? '');
                if ($sku !== '') {
                    $bySku[$sku] = $index;
                }
            }

            foreach ($pending as $change) {
                if (! isset($bySku[$change->sku])) {
                    continue;
                }
                $idx = $bySku[$change->sku];
                $products[$idx][$change->csv_column] = number_format((float) $change->new_price, 2, '.', '');
            }

            $this->zeroPriceGuard->assertNoZeroPrices(
                $pending->map(fn (PendingPriceChange $c) => new Offer(
                    sku: $c->sku,
                    price: (float) $c->new_price,
                ))
            );

            $this->updateProgress($user->id, 40, 'Écriture du flux sur le stockage…');
            $this->feedIO->write($feed, $headers, $products);
            $this->snapshots->persistProducts($feed, $headers, $products);

            $this->updateProgress($user->id, 55, 'Enregistrement de l’historique…');
            DB::transaction(function () use ($pending, $user, $feed) {
                foreach ($pending as $change) {
                    PriceChangeLog::query()->create([
                        'user_id' => $user->id,
                        'feed_file_id' => $feed->id,
                        'sku' => $change->sku,
                        'csv_column' => $change->csv_column,
                        'old_price' => $change->old_price,
                        'new_price' => $change->new_price,
                    ]);
                }
                PendingPriceChange::query()
                    ->where('user_id', $user->id)
                    ->where('feed_file_id', $feed->id)
                    ->delete();
            });

            $offers = [];
            foreach ($pending->groupBy('sku') as $sku => $changes) {
                $ean = null;
                if (isset($bySku[$sku])) {
                    $ean = $this->parser->extractEan($products[$bySku[$sku]]);
                }
                $primary = $changes->first();
                $offers[] = new Offer(
                    sku: (string) $sku,
                    price: (float) $primary->new_price,
                    ean: $ean,
                );
            }

            $messages = [];
            $marketplaces = Marketplace::query()
                ->whereIn('id', $marketplaceIds)
                ->where('is_active', true)
                ->get();

            $step = 0;
            $total = max(1, $marketplaces->count());
            foreach ($marketplaces as $marketplace) {
                $step++;
                $pct = 60 + (int) floor(($step / $total) * 35);
                $this->updateProgress($user->id, $pct, "Push API {$marketplace->name}…");

                $client = $this->marketplaceFactory->make($marketplace);
                $result = $client->pushPrices($marketplace, $offers);
                $messages[] = "{$marketplace->name}: ".($result->success ? 'OK' : $result->message);

                if (! $result->success) {
                    throw new \RuntimeException(implode(' | ', $messages));
                }
            }

            $batch->update([
                'status' => PushBatchStatus::Succeeded,
                'offers_count' => count($offers),
                'result_message' => implode(' | ', $messages) ?: 'Flux synchronisé.',
                'finished_at' => now(),
            ]);

            $this->updateProgress($user->id, 100, 'Terminé');
            Cache::put($lockKey, [
                'status' => 'done',
                'progress' => 100,
                'label' => 'Publication terminée',
                'feed_file_id' => $feed->id,
                'batch_id' => $batch->id,
            ], now()->addMinutes(10));

            return $batch->fresh();
        } catch (\Throwable $e) {
            $batch->update([
                'status' => PushBatchStatus::Failed,
                'result_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Cache::put($lockKey, [
                'status' => 'failed',
                'progress' => 100,
                'label' => $e->getMessage(),
                'feed_file_id' => $feed->id,
                'batch_id' => $batch->id,
            ], now()->addMinutes(10));

            throw $e;
        }
    }

    private function updateProgress(int $userId, int $progress, string $label): void
    {
        Cache::put("publish_lock_user_{$userId}", [
            'status' => 'running',
            'progress' => $progress,
            'label' => $label,
        ], now()->addHour());
    }
}
