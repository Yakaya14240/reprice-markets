<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'symbol', 'decimals', 'is_active', 'sort_order'])]
class Currency extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'decimals' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function priceColumns(): HasMany
    {
        return $this->hasMany(PriceColumn::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        $symbol = $this->symbol ? " ({$this->symbol})" : '';

        return "{$this->code} — {$this->name}{$symbol}";
    }
}
