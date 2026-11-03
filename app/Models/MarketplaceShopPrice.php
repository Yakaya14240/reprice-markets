<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['marketplace_id', 'shop_id', 'price_column_id'])]
class MarketplaceShopPrice extends Model
{
    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function priceColumn(): BelongsTo
    {
        return $this->belongsTo(PriceColumn::class);
    }
}
