<?php

namespace App\Jobs;

use App\Models\FeedFile;
use App\Models\User;
use App\Services\Publish\PublishPendingChangesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PublishPendingChangesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $marketplaceIds
     */
    public function __construct(
        public int $userId,
        public int $feedFileId,
        public array $marketplaceIds,
    ) {}

    public function handle(PublishPendingChangesService $publisher): void
    {
        $user = User::query()->find($this->userId);
        $feed = FeedFile::query()->find($this->feedFileId);

        if (! $user || ! $feed) {
            return;
        }

        try {
            $publisher->publish($user, $feed, $this->marketplaceIds);
        } catch (\Throwable $e) {
            Log::error('PublishPendingChangesJob failed', [
                'user_id' => $this->userId,
                'feed_file_id' => $this->feedFileId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
