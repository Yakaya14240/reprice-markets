<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['feed_file_id', 'name', 'csv_column', 'alias', 'currency_id', 'is_active', 'is_public_price', 'is_chart_default'])]
class PriceColumn extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public_price' => 'boolean',
            'is_chart_default' => 'boolean',
        ];
    }

    public function feedFile(): BelongsTo
    {
        return $this->belongsTo(FeedFile::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function marketplaceMappings(): HasMany
    {
        return $this->hasMany(MarketplacePriceMapping::class);
    }
}
