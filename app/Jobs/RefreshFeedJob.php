<?php

namespace App\Jobs;

use App\Models\FeedFile;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshFeedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $feedFileId) {}

    public function handle(FeedSnapshotService $snapshots): void
    {
        $feed = FeedFile::query()->find($this->feedFileId);
        if (! $feed || ! $feed->is_active) {
            return;
        }

        try {
            $snapshots->refresh($feed);
        } catch (\Throwable $e) {
            Log::error('RefreshFeedJob failed', [
                'feed_file_id' => $this->feedFileId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
