<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'feed_file_id',
    'sku',
    'product_sheet_field_id',
    'csv_column',
    'old_value',
    'new_value',
])]
class SheetChangeLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feedFile(): BelongsTo
    {
        return $this->belongsTo(FeedFile::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ProductSheetField::class, 'product_sheet_field_id');
    }
}
