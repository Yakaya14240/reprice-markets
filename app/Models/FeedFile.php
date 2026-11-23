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
    'description_column',
    'image_columns',
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
            'image_columns' => 'array',
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
            // URL is read-only unless an explicit writable destination is configured.
            return FeedSourceType::Url;
        }

        return $this->source_type;
    }

    public function effectiveWriteLocation(): string
    {
        if (filled($this->write_location)) {
            return (string) $this->write_location;
        }

        if ($this->source_type === FeedSourceType::Url) {
            return '';
        }

        return (string) $this->location;
    }

    public function hasWritableDestination(): bool
    {
        $type = $this->effectiveWriteSourceType();
        $location = $this->effectiveWriteLocation();

        return $type->canWrite() && $location !== '';
    }

    public function writeDestinationLabel(): string
    {
        if (! $this->hasWritableDestination()) {
            return 'non configurée';
        }

        $type = $this->effectiveWriteSourceType();

        return $type->label().' → '.$this->effectiveWriteLocation();
    }

    /**
     * Column names discovered from the last successful feed load.
     *
     * @return list<string>
     */
    public function availableHeaders(): array
    {
        $headers = $this->snapshot?->headers ?? [];
        if (! is_array($headers)) {
            return [];
        }

        $headers = array_values(array_filter(
            array_map(static fn ($h) => trim((string) $h), $headers),
            static fn (string $h) => $h !== '',
        ));

        natcasesort($headers);

        return array_values($headers);
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
     * @param  list<string>|null  $headers
     */
    public function resolveDescriptionColumn(?array $headers = null): ?string
    {
        $configured = trim((string) $this->description_column);
        if ($configured === '') {
            return null;
        }

        $headers = $headers ?? $this->snapshot?->headers;
        if (is_array($headers) && $headers !== [] && ! in_array($configured, $headers, true)) {
            return null;
        }

        return $configured;
    }

    /**
     * @param  list<string>|null  $headers
     * @return list<string>
     */
    public function resolveImageColumns(?array $headers = null): array
    {
        $configured = $this->image_columns ?? [];
        if (! is_array($configured)) {
            return [];
        }

        $headers = $headers ?? $this->snapshot?->headers;
        $columns = [];
        foreach ($configured as $column) {
            $name = trim((string) $column);
            if ($name === '') {
                continue;
            }
            if (is_array($headers) && $headers !== [] && ! in_array($name, $headers, true)) {
                continue;
            }
            $columns[] = $name;
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param  array<string, string|null>  $product
     * @param  list<string>|null  $headers
     */
    public function productDescription(array $product, ?array $headers = null): string
    {
        $column = $this->resolveDescriptionColumn($headers);
        if ($column === null) {
            return '';
        }

        return self::plainTextFromHtml((string) ($product[$column] ?? ''));
    }

    /**
     * Strip markup for display: tags removed, entities decoded, whitespace collapsed.
     */
    public static function plainTextFromHtml(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/<\s*br\s*\/?\s*>/i', ' ', $decoded) ?? $decoded;
        $normalized = preg_replace('/<\/\s*(p|div|li|h[1-6])\s*>/i', ' ', $normalized) ?? $normalized;
        $stripped = strip_tags($normalized);
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?? $stripped;

        return trim($collapsed);
    }

    /**
     * @param  array<string, string|null>  $product
     * @param  list<string>|null  $headers
     * @return list<string>
     */
    public function productImages(array $product, ?array $headers = null): array
    {
        $images = [];
        foreach ($this->resolveImageColumns($headers) as $column) {
            $raw = trim((string) ($product[$column] ?? ''));
            if ($raw === '') {
                continue;
            }
            foreach (preg_split('/\s*[|,;]\s*/', $raw) ?: [] as $url) {
                $url = trim((string) $url);
                if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                    $images[] = $url;
                }
            }
        }

        return array_values(array_unique($images));
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
