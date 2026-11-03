<?php

namespace App\Models;

use App\Enums\FeedSnapshotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'feed_file_id',
    'status',
    'products_count',
    'storage_path',
    'headers',
    'error_message',
    'progress',
    'progress_label',
    'loaded_at',
])]
class FeedSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'status' => FeedSnapshotStatus::class,
            'headers' => 'array',
            'loaded_at' => 'datetime',
        ];
    }

    public function feedFile(): BelongsTo
    {
        return $this->belongsTo(FeedFile::class);
    }
}
