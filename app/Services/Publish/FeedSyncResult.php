<?php

namespace App\Services\Publish;

use App\Models\PendingPriceChange;
use App\Models\PricePushBatch;
use Illuminate\Support\Collection;

final readonly class FeedSyncResult
{
    /**
     * @param  Collection<int, PendingPriceChange>  $pending
     * @param  list<string>  $headers
     * @param  list<array<string, string|null>>  $products
     */
    public function __construct(
        public PricePushBatch $batch,
        public Collection $pending,
        public array $headers,
        public array $products,
        public string $writeLabel,
        public int $changesCount,
    ) {}
}
