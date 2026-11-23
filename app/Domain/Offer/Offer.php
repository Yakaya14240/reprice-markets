<?php

namespace App\Domain\Offer;

final readonly class Offer
{
    /**
     * @param  array<int, array{
     *     id: string,
     *     price: float,
     *     discount_price?: float|null,
     *     discount_start_date?: string|null,
     *     discount_end_date?: string|null,
     *     sale_price?: float,
     *     needs_mirakl_fallback?: bool,
     *     needs_mirakl_dates?: bool
     * }>  $channels
     */
    public function __construct(
        public string $sku,
        public float $price,
        public ?string $ean = null,
        public ?string $shopId = null,
        public array $channels = [],
        public ?float $discountPrice = null,
        public ?string $discountStartDate = null,
        public ?string $discountEndDate = null,
        public ?float $salePrice = null,
        public bool $needsMiraklFallback = false,
        public bool $needsMiraklDates = false,
    ) {}

    public function hasZeroOrNegativePrice(): bool
    {
        if ($this->price <= 0) {
            return true;
        }

        if ($this->discountPrice !== null && $this->discountPrice <= 0) {
            return true;
        }

        foreach ($this->channels as $channel) {
            if (($channel['price'] ?? 0) <= 0) {
                return true;
            }
            if (isset($channel['discount_price']) && $channel['discount_price'] !== null && $channel['discount_price'] <= 0) {
                return true;
            }
        }

        return false;
    }

    public function needsMiraklEnrichment(): bool
    {
        if ($this->needsMiraklFallback || $this->needsMiraklDates) {
            return true;
        }

        foreach ($this->channels as $channel) {
            if (($channel['needs_mirakl_fallback'] ?? false) || ($channel['needs_mirakl_dates'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
