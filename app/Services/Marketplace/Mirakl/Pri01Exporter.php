<?php

namespace App\Services\Marketplace\Mirakl;

use App\Domain\Offer\Offer;

/**
 * Builds Mirakl PRI01 pricing import CSV variants.
 */
final class Pri01Exporter
{
    /**
     * @param  list<Offer>  $offers
     */
    public function export(array $offers, bool $withChannels = false, bool $withShop = false): string
    {
        $headers = ['offer-sku', 'price', 'discount-price', 'discount-start-date', 'discount-end-date'];

        if ($withChannels) {
            $headers[] = 'channels';
        }

        if ($withShop) {
            $headers[] = 'shop';
        }

        $lines = [implode(';', $headers)];

        foreach ($offers as $offer) {
            if ($withChannels && $offer->channels !== []) {
                foreach ($offer->channels as $channel) {
                    $lines[] = implode(';', [
                        $offer->sku,
                        $this->formatPrice($channel['price']),
                        $offer->discountPrice !== null ? $this->formatPrice($offer->discountPrice) : '',
                        $offer->discountStartDate ?? '',
                        $offer->discountEndDate ?? '',
                        $channel['id'],
                    ]);
                }

                continue;
            }

            $row = [
                $offer->sku,
                $this->formatPrice($offer->price),
                $offer->discountPrice !== null ? $this->formatPrice($offer->discountPrice) : '',
                $offer->discountStartDate ?? '',
                $offer->discountEndDate ?? '',
            ];

            if ($withChannels) {
                $row[] = '';
            }

            if ($withShop) {
                $row[] = $offer->shopId ?? '';
            }

            $lines[] = implode(';', $row);
        }

        return implode("\n", $lines)."\n";
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }
}
