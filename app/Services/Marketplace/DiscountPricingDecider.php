<?php

namespace App\Services\Marketplace;

use App\Domain\Pricing\DiscountPricingDecision;

/**
 * Decide PRI01 unit vs discount from CSV public price + marketplace sale price.
 */
final class DiscountPricingDecider
{
    /**
     * @param  bool  $sameCurrency  When false, never create a discount (cross-currency compare is unsafe).
     */
    public function decide(?float $publicPrice, float $salePrice, bool $sameCurrency = true): DiscountPricingDecision
    {
        if (! $sameCurrency) {
            return new DiscountPricingDecision(
                unitPrice: $salePrice,
                discountPrice: null,
                salePrice: $salePrice,
                needsMiraklFallback: false,
                needsMiraklDates: false,
            );
        }

        if ($publicPrice === null || $publicPrice <= 0) {
            return new DiscountPricingDecision(
                unitPrice: $salePrice,
                discountPrice: null,
                salePrice: $salePrice,
                needsMiraklFallback: true,
                needsMiraklDates: false,
            );
        }

        if ($salePrice < $publicPrice) {
            return new DiscountPricingDecision(
                unitPrice: $publicPrice,
                discountPrice: $salePrice,
                salePrice: $salePrice,
                needsMiraklFallback: false,
                needsMiraklDates: true,
            );
        }

        return new DiscountPricingDecision(
            unitPrice: $salePrice,
            discountPrice: null,
            salePrice: $salePrice,
            needsMiraklFallback: false,
            needsMiraklDates: false,
        );
    }
}
