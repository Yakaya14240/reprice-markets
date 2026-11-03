<?php

namespace App\Models;

use App\Enums\MarketplaceDriver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'driver',
    'api_url',
    'api_key',
    'client_key',
    'client_secret',
    'shop_ids',
    'supports_channels',
    'supports_multi_shop',
    'is_active',
])]
#[Hidden(['api_key', 'client_key', 'client_secret'])]
class Marketplace extends Model
{
    protected function casts(): array
    {
        return [
            'driver' => MarketplaceDriver::class,
            'api_key' => 'encrypted',
            'client_key' => 'encrypted',
            'client_secret' => 'encrypted',
            'shop_ids' => 'array',
            'supports_channels' => 'boolean',
            'supports_multi_shop' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function shopPrices(): HasMany
    {
        return $this->hasMany(MarketplaceShopPrice::class);
    }

    public function channelMappings(): HasMany
    {
        return $this->hasMany(ChannelPriceMapping::class);
    }

    public function pushBatches(): HasMany
    {
        return $this->hasMany(PricePushBatch::class);
    }
}
