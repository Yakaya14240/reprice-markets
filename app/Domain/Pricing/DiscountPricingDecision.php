<?php

namespace App\Domain\Pricing;

final readonly class DiscountPricingDecision
{
    public function __construct(
        public float $unitPrice,
        public ?float $discountPrice,
        public float $salePrice,
        public bool $needsMiraklFallback,
        public bool $needsMiraklDates,
    ) {}
}
