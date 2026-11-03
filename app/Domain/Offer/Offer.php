<?php

namespace App\Domain\Offer;

final readonly class Offer
{
    /**
     * @param  array<int, array{id: string, price: float}>  $channels
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
    ) {}

    public function hasZeroOrNegativePrice(): bool
    {
        if ($this->price <= 0) {
            return true;
        }

        foreach ($this->channels as $channel) {
            if (($channel['price'] ?? 0) <= 0) {
                return true;
            }
        }

        return false;
    }
}
