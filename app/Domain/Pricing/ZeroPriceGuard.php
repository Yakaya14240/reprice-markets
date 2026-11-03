<?php

namespace App\Domain\Pricing;

use App\Domain\Offer\Offer;

final class ZeroPriceGuard
{
    /**
     * @param  iterable<Offer>  $offers
     * @return list<string>
     */
    public function collectViolations(iterable $offers): array
    {
        $violations = [];

        foreach ($offers as $offer) {
            if ($offer->price <= 0) {
                $violations[] = "SKU {$offer->sku}: prix global = {$offer->price}";
            }

            foreach ($offer->channels as $channel) {
                if (($channel['price'] ?? 0) <= 0) {
                    $violations[] = "SKU {$offer->sku}: channel {$channel['id']} = {$channel['price']}";
                }
            }
        }

        return $violations;
    }

    /**
     * @param  iterable<Offer>  $offers
     */
    public function assertNoZeroPrices(iterable $offers): void
    {
        $violations = $this->collectViolations($offers);

        if ($violations !== []) {
            throw new ZeroPriceBlockedException($violations);
        }
    }
}
