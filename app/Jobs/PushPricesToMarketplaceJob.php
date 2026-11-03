<?php

namespace App\Jobs;

use App\Enums\PushBatchStatus;
use App\Models\PricePushBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Queue-ready stub: assemble offers + call MarketplaceClientFactory in the next iteration.
 */
class PushPricesToMarketplaceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        $batch = PricePushBatch::query()->find($this->batchId);
        if (! $batch) {
            return;
        }

        $batch->update([
            'status' => PushBatchStatus::Running,
            'started_at' => now(),
            'result_message' => 'Job scaffold ready — wire offer assembly + marketplace client next.',
        ]);

        Log::info('PushPricesToMarketplaceJob placeholder executed', ['batch_id' => $batch->id]);

        $batch->update([
            'status' => PushBatchStatus::Succeeded,
            'finished_at' => now(),
            'result_message' => 'Placeholder success. Implement offer resolution before production use.',
        ]);
    }
}
