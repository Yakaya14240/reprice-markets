<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['marketplace_id', 'shop_id', 'channel_code', 'channel_name', 'price_column_id'])]
class MarketplacePriceMapping extends Model
{
    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function priceColumn(): BelongsTo
    {
        return $this->belongsTo(PriceColumn::class);
    }

    public function hasChannel(): bool
    {
        return filled($this->channel_code);
    }

    public function channelLabel(): string
    {
        if (! $this->hasChannel()) {
            return '—';
        }

        $name = $this->channel_name ?: $this->channel_code;

        return "{$this->shop_id} — {$name} ({$this->channel_code})";
    }
}
