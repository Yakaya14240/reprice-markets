<?php

namespace App\Services\Publish;

use App\Domain\Offer\Offer;
use App\Domain\Pricing\ZeroPriceGuard;
use App\Enums\PushBatchStatus;
use App\Exceptions\FeedPublishLockException;
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
use App\Services\Marketplace\OfferAssembler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PublishPendingChangesService
{
    public function __construct(
        private readonly FeedSnapshotService $snapshots,
        private readonly FeedIO $feedIO,
        private readonly LengowCsvParser $parser,
        private readonly MarketplaceClientFactory $marketplaceFactory,
        private readonly OfferAssembler $offerAssembler,
        private readonly ZeroPriceGuard $zeroPriceGuard,
    ) {}

    /**
     * @param  list<int>  $marketplaceIds
     */
    public function publish(User $user, FeedFile $feed, array $marketplaceIds, ?string $note = null): PricePushBatch
    {
        $lockKey = "publish_lock_user_{$user->id}";
        $note = filled($note) ? trim($note) : null;
        $feedLock = Cache::lock("publish_lock_feed_{$feed->id}", 600);

        if (! $feedLock->get()) {
            $holder = Cache::get("publish_feed_holder_{$feed->id}", []);
            $holderName = isset($holder['name']) ? (string) $holder['name'] : null;
            $holderUserId = isset($holder['user_id']) ? (int) $holder['user_id'] : null;
            $message = $holderName
                ? "Une publication est déjà en cours sur ce flux (par {$holderName})."
                : 'Une publication est déjà en cours sur ce flux.';

            throw new FeedPublishLockException(
                message: $message,
                holderUserId: $holderUserId,
                holderName: $holderName,
            );
        }

        Cache::put("publish_feed_holder_{$feed->id}", [
            'user_id' => $user->id,
            'name' => $user->name,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        try {
            $sync = $this->syncFeed($user, $feed, $marketplaceIds, $note);

            if ($marketplaceIds === []) {
                $sync->batch->update([
                    'status' => PushBatchStatus::Succeeded,
                    'offers_count' => 0,
                    'result_message' => "Flux synchronisé vers {$sync->writeLabel}.",
                    'finished_at' => now(),
                ]);

                $this->finishProgress($user->id, $feed->id, $sync->batch->id, 'Flux synchronisé');

                return $sync->batch->fresh();
            }

            $messages = [];
            $marketplaces = Marketplace::query()
                ->whereIn('id', $marketplaceIds)
                ->where('is_active', true)
                ->with(['priceMappings.priceColumn'])
                ->get();

            if ($marketplaces->isEmpty()) {
                $sync->batch->update([
                    'status' => PushBatchStatus::Succeeded,
                    'offers_count' => 0,
                    'result_message' => "Flux synchronisé vers {$sync->writeLabel}. Aucune marketplace active sélectionnée.",
                    'finished_at' => now(),
                ]);
                $this->finishProgress($user->id, $feed->id, $sync->batch->id, 'Flux synchronisé');

                return $sync->batch->fresh();
            }

            $step = 0;
            $total = max(1, $marketplaces->count());
            $allOk = true;
            $offersPushed = 0;

            foreach ($marketplaces as $marketplace) {
                $step++;
                $pct = 65 + (int) floor(($step / $total) * 30);
                $this->updateProgress($user->id, $pct, "Push API {$marketplace->name}…", $feed->id);

                try {
                    $offers = $this->offerAssembler->offersFor(
                        $marketplace,
                        $feed,
                        $sync->pending,
                        $sync->headers,
                        $sync->products,
                    );

                    if ($offers === []) {
                        $messages[] = "{$marketplace->name}: aucune offre à pousser (mapping / prix)";

                        continue;
                    }

                    $client = $this->marketplaceFactory->make($marketplace);
                    $result = $client->pushPrices($marketplace, $offers);
                    $offersPushed += count($offers);
                    $messages[] = "{$marketplace->name}: ".($result->success ? 'OK ('.count($offers).' offres)' : $result->message);
                    if (! $result->success) {
                        $allOk = false;
                    }
                } catch (\Throwable $e) {
                    $allOk = false;
                    $messages[] = "{$marketplace->name}: {$e->getMessage()}";
                }
            }

            $mkpSummary = implode(' | ', $messages);
            if ($allOk) {
                $sync->batch->update([
                    'status' => PushBatchStatus::Succeeded,
                    'offers_count' => $offersPushed,
                    'result_message' => "Flux sync OK ({$sync->writeLabel}). {$mkpSummary}",
                    'finished_at' => now(),
                ]);
                $this->finishProgress($user->id, $feed->id, $sync->batch->id, 'Publication terminée');
            } else {
                $sync->batch->update([
                    'status' => PushBatchStatus::Failed,
                    'offers_count' => $offersPushed,
                    'result_message' => "Flux synchronisé vers {$sync->writeLabel}, mais API marketplace en échec : {$mkpSummary}",
                    'finished_at' => now(),
                ]);
                Cache::put($lockKey, [
                    'status' => 'partial',
                    'progress' => 100,
                    'label' => "Flux sync OK, API en échec : {$mkpSummary}",
                    'feed_file_id' => $feed->id,
                    'batch_id' => $sync->batch->id,
                    'sync_ok' => true,
                ], now()->addMinutes(10));
            }

            return $sync->batch->fresh();
        } catch (\Throwable $e) {
            Cache::put($lockKey, [
                'status' => 'failed',
                'progress' => 100,
                'label' => $e->getMessage(),
                'feed_file_id' => $feed->id,
            ], now()->addMinutes(10));

            throw $e;
        } finally {
            Cache::forget("publish_feed_holder_{$feed->id}");
            $feedLock->release();
        }
    }

    /**
     * Apply pending price changes, write CSV to configured storage, audit, clear pending.
     *
     * @param  list<int>  $marketplaceIds
     */
    public function syncFeed(User $user, FeedFile $feed, array $marketplaceIds = [], ?string $note = null): FeedSyncResult
    {
        if (! $feed->hasWritableDestination()) {
            throw new RuntimeException(
                'Destination d’écriture non configurée. Pour un flux URL, renseignez le type et le chemin d’écriture (local ou SFTP) en admin.'
            );
        }

        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '1024M');
            @ini_set('max_execution_time', '300');
        }

        $this->updateProgress($user->id, 5, 'Préparation du sync flux…', $feed->id);

        $primaryMarketplaceId = $marketplaceIds[0]
            ?? Marketplace::query()->value('id');

        if (! $primaryMarketplaceId) {
            throw new RuntimeException('Aucune marketplace disponible pour journaliser la publication.');
        }

        $batch = PricePushBatch::query()->create([
            'user_id' => $user->id,
            'marketplace_id' => $primaryMarketplaceId,
            'feed_file_id' => $feed->id,
            'status' => PushBatchStatus::Running,
            'offers_count' => 0,
            'note' => filled($note) ? trim($note) : null,
            'payload' => [
                'marketplace_ids' => $marketplaceIds,
                'write_label' => $feed->writeDestinationLabel(),
            ],
            'started_at' => now(),
        ]);

        try {
            $pending = PendingPriceChange::query()
                ->where('user_id', $user->id)
                ->where('feed_file_id', $feed->id)
                ->get();

            if ($pending->isEmpty()) {
                throw new RuntimeException('Aucune modification en attente.');
            }

            $this->updateProgress($user->id, 15, 'Application des modifications au flux…', $feed->id);

            $data = $this->snapshots->loadProducts($feed);
            $headers = $data['headers'];
            $products = $data['products'];
            $skuColumn = $feed->resolveSkuColumn($headers);

            $bySku = [];
            foreach ($products as $index => $product) {
                $sku = $feed->productSku($product, $headers);
                if ($sku !== '') {
                    $bySku[$sku] = $index;
                }
            }

            $applied = 0;
            foreach ($pending as $change) {
                if (! isset($bySku[$change->sku])) {
                    continue;
                }
                $idx = $bySku[$change->sku];
                $products[$idx][$change->csv_column] = number_format((float) $change->new_price, 2, '.', '');
                $applied++;
            }

            if ($applied === 0) {
                throw new RuntimeException(
                    "Aucune ligne du flux ne correspond aux SKU en attente (colonne utilisée : {$skuColumn})."
                );
            }

            $this->zeroPriceGuard->assertNoZeroPrices(
                $pending->map(fn (PendingPriceChange $c) => new Offer(
                    sku: $c->sku,
                    price: (float) $c->new_price,
                ))
            );

            $writeLabel = $feed->writeDestinationLabel();
            $this->updateProgress($user->id, 40, "Écriture du flux ({$writeLabel})…", $feed->id);
            $this->feedIO->write($feed, $headers, $products);

            $this->updateProgress($user->id, 50, 'Enregistrement de l’historique…', $feed->id);
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

            $this->updateProgress($user->id, 55, 'Mise à jour du snapshot local…', $feed->id);
            try {
                $this->snapshots->persistProducts($feed, $headers, $products);
            } catch (\Throwable $e) {
                Log::warning('Snapshot persist failed after successful feed write', [
                    'feed_file_id' => $feed->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->updateProgress($user->id, 60, 'Sync flux terminé', $feed->id);

            $batch->update([
                'offers_count' => $pending->pluck('sku')->unique()->count(),
                'result_message' => "Flux synchronisé vers {$writeLabel} ({$applied} modification(s)).",
                'payload' => array_merge($batch->payload ?? [], [
                    'applied' => $applied,
                    'write_label' => $writeLabel,
                    'sync_ok' => true,
                ]),
            ]);

            return new FeedSyncResult(
                batch: $batch->fresh(),
                pending: $pending,
                headers: $headers,
                products: $products,
                writeLabel: $writeLabel,
                changesCount: $applied,
            );
        } catch (\Throwable $e) {
            $batch->update([
                'status' => PushBatchStatus::Failed,
                'result_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function updateProgress(int $userId, int $progress, string $label, ?int $feedId = null): void
    {
        $key = "publish_lock_user_{$userId}";
        $current = Cache::get($key, []);

        Cache::put($key, [
            'status' => 'running',
            'progress' => $progress,
            'label' => $label,
            'feed_file_id' => $feedId,
            'started_at' => $current['started_at'] ?? now()->toIso8601String(),
        ], now()->addMinutes(30));
    }

    private function finishProgress(int $userId, int $feedId, int $batchId, string $label): void
    {
        Cache::put("publish_lock_user_{$userId}", [
            'status' => 'done',
            'progress' => 100,
            'label' => $label,
            'feed_file_id' => $feedId,
            'batch_id' => $batchId,
            'sync_ok' => true,
        ], now()->addMinutes(10));
    }
}
