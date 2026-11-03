<?php

namespace App\Models;

use App\Enums\FeedSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'source_type',
    'location',
    'write_source_type',
    'write_location',
    'connection_config',
    'delimiter',
    'sku_column',
    'name_column',
    'is_active',
])]
#[Hidden(['connection_config'])]
class FeedFile extends Model
{
    protected function casts(): array
    {
        return [
            'source_type' => FeedSourceType::class,
            'write_source_type' => FeedSourceType::class,
            'connection_config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function priceColumns(): HasMany
    {
        return $this->hasMany(PriceColumn::class);
    }

    public function priceChangeLogs(): HasMany
    {
        return $this->hasMany(PriceChangeLog::class);
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(FeedSnapshot::class);
    }

    public function pendingPriceChanges(): HasMany
    {
        return $this->hasMany(PendingPriceChange::class);
    }

    public function effectiveWriteSourceType(): FeedSourceType
    {
        if ($this->write_source_type instanceof FeedSourceType) {
            return $this->write_source_type;
        }

        if ($this->source_type === FeedSourceType::Url) {
            return FeedSourceType::Sftp;
        }

        return $this->source_type;
    }

    public function effectiveWriteLocation(): string
    {
        return $this->write_location ?: (string) $this->location;
    }

    /**
     * @param  list<string>|null  $headers
     */
    public function resolveSkuColumn(?array $headers = null): string
    {
        $configured = trim((string) $this->sku_column);
        $headers = $headers ?? $this->snapshot?->headers;

        if ($configured !== '' && (is_null($headers) || in_array($configured, $headers, true))) {
            return $configured;
        }

        foreach (['Identifiant_unique', 'SKU', 'sku', 'offer-sku', 'ID'] as $candidate) {
            if (is_array($headers) && in_array($candidate, $headers, true)) {
                return $candidate;
            }
        }

        return $configured !== '' ? $configured : 'Identifiant_unique';
    }

    /**
     * @param  list<string>|null  $headers
     */
    public function resolveNameColumn(?array $headers = null): string
    {
        $configured = trim((string) $this->name_column);
        $headers = $headers ?? $this->snapshot?->headers;

        if ($configured !== '' && (is_null($headers) || in_array($configured, $headers, true))) {
            return $configured;
        }

        foreach (['Titre', 'Title', 'title', 'name', 'Nom'] as $candidate) {
            if (is_array($headers) && in_array($candidate, $headers, true)) {
                return $candidate;
            }
        }

        return $configured !== '' ? $configured : 'Titre';
    }

    /**
     * @param  array<string, string|null>  $product
     * @param  list<string>|null  $headers
     */
    public function productSku(array $product, ?array $headers = null): string
    {
        $column = $this->resolveSkuColumn($headers);
        $sku = trim((string) ($product[$column] ?? ''));
        if ($sku !== '') {
            return $sku;
        }

        foreach (['Identifiant_unique', 'SKU', 'sku', 'EAN', 'ean'] as $candidate) {
            $value = trim((string) ($product[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
