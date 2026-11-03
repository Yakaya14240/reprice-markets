<?php

namespace App\Models;

use App\Enums\PushBatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'marketplace_id',
    'feed_file_id',
    'status',
    'offers_count',
    'payload',
    'result_message',
    'started_at',
    'finished_at',
])]
class PricePushBatch extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PushBatchStatus::class,
            'payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function feedFile(): BelongsTo
    {
        return $this->belongsTo(FeedFile::class);
    }
}
