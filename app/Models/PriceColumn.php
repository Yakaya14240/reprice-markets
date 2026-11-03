<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['feed_file_id', 'name', 'csv_column', 'alias', 'currency_id', 'is_active'])]
class PriceColumn extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function shopMappings(): HasMany
    {
        return $this->hasMany(MarketplaceShopPrice::class);
    }

    public function channelMappings(): HasMany
    {
        return $this->hasMany(ChannelPriceMapping::class);
    }
}
