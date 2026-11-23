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
            'is_active' => 'boolean',
        ];
    }

    public function priceMappings(): HasMany
    {
        return $this->hasMany(MarketplacePriceMapping::class);
    }

    public function pushBatches(): HasMany
    {
        return $this->hasMany(PricePushBatch::class);
    }
}
