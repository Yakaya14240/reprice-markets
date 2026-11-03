<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['marketplace_id', 'channel_code', 'channel_name', 'price_column_id'])]
class ChannelPriceMapping extends Model
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
