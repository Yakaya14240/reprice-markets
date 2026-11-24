<?php

namespace App\Models;

use App\Enums\ProductSheetFieldType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'feed_file_id',
    'language_id',
    'type',
    'label',
    'product_sheet_group_id',
    'csv_column',
    'is_editable',
    'max_length',
    'help_text',
    'sort_order',
    'settings',
])]
class ProductSheetField extends Model
{
    public const DEFAULT_GROUP_NAME = 'Autres';

    protected function casts(): array
    {
        return [
            'type' => ProductSheetFieldType::class,
            'is_editable' => 'boolean',
            'max_length' => 'integer',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    public function feedFile(): BelongsTo
    {
        return $this->belongsTo(FeedFile::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductSheetGroup::class, 'product_sheet_group_id');
    }

    public function pendingChanges(): HasMany
    {
        return $this->hasMany(PendingSheetChange::class);
    }

    public function maxImages(): int
    {
        $max = (int) ($this->settings['max_images'] ?? 10);

        return max(1, min(20, $max));
    }

    public function groupLabel(): string
    {
        return $this->group?->name ?: self::DEFAULT_GROUP_NAME;
    }
}
