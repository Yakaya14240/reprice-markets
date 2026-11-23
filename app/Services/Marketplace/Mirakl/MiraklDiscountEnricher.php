<?php

namespace App\Services\Marketplace\Mirakl;

use App\Domain\Offer\Offer;
use App\Models\Marketplace;

/**
 * Apply Mirakl-state fallback / preserve discount dates before PRI01.
 */
final class MiraklDiscountEnricher
{
    public function __construct(private readonly MiraklOfferPricingReader $reader) {}

    /**
     * @param  list<Offer>  $offers
     * @return list<Offer>
     */
    public function enrich(Marketplace $marketplace, array $offers): array
    {
        $toEnrich = array_values(array_filter(
            $offers,
            static fn (Offer $offer): bool => $offer->needsMiraklEnrichment()
        ));

        if ($toEnrich === []) {
            return $offers;
        }

        /** @var array<string, list<Offer>> $byShop */
        $byShop = [];
        foreach ($toEnrich as $offer) {
            $shopId = $offer->shopId ?? ($marketplace->shop_ids[0] ?? null);
            if ($shopId === null || $shopId === '') {
                continue;
            }
            $byShop[(string) $shopId][] = $offer;
        }

        /** @var array<string, array<string, mixed>> $remoteByShopSku */
        $remoteByShopSku = [];
        foreach ($byShop as $shopId => $shopOffers) {
            $skus = array_values(array_unique(array_map(
                static fn (Offer $o): string => $o->sku,
                $shopOffers
            )));
            $remoteByShopSku[$shopId] = $this->reader->pricingBySku($marketplace, $shopId, $skus);
        }

        $enriched = [];
        foreach ($offers as $offer) {
            if (! $offer->needsMiraklEnrichment()) {
                $enriched[] = $offer;

                continue;
            }

            $shopId = (string) ($offer->shopId ?? ($marketplace->shop_ids[0] ?? ''));
            $remote = $remoteByShopSku[$shopId][$offer->sku] ?? null;
            $enriched[] = $this->enrichOne($offer, is_array($remote) ? $remote : null);
        }

        return $enriched;
    }

    /**
     * @param  array{
     *     origin: float|null,
     *     discount: float|null,
     *     discount_start_date: string|null,
     *     discount_end_date: string|null,
     *     channels: array<string, array{
     *         origin: float|null,
     *         discount: float|null,
     *         discount_start_date: string|null,
     *         discount_end_date: string|null
     *     }>
     * }|null  $remote
     */
    private function enrichOne(Offer $offer, ?array $remote): Offer
    {
        if ($offer->channels !== []) {
            return $this->enrichChannelOffer($offer, $remote);
        }

        return $this->enrichMonoOffer($offer, $remote);
    }

    /**
     * @param  array<string, mixed>|null  $remote
     */
    private function enrichMonoOffer(Offer $offer, ?array $remote): Offer
    {
        $sale = $offer->salePrice ?? $offer->price;
        $state = $this->defaultState($remote);

        if ($offer->needsMiraklFallback) {
            $applied = $this->applyFallback($sale, $state);

            return new Offer(
                sku: $offer->sku,
                price: $applied['price'],
                ean: $offer->ean,
                shopId: $offer->shopId,
                discountPrice: $applied['discount_price'],
                discountStartDate: $applied['discount_start_date'],
                discountEndDate: $applied['discount_end_date'],
                salePrice: $sale,
            );
        }

        if ($offer->needsMiraklDates && $offer->discountPrice !== null) {
            return new Offer(
                sku: $offer->sku,
                price: $offer->price,
                ean: $offer->ean,
                shopId: $offer->shopId,
                discountPrice: $offer->discountPrice,
                discountStartDate: $state['discount_start_date'],
                discountEndDate: $state['discount_end_date'],
                salePrice: $sale,
            );
        }

        return $offer;
    }

    /**
     * @param  array<string, mixed>|null  $remote
     */
    private function enrichChannelOffer(Offer $offer, ?array $remote): Offer
    {
        $channels = [];
        $primaryPrice = null;
        $primaryDiscount = null;
        $primarySale = null;

        foreach ($offer->channels as $channel) {
            $channelId = (string) $channel['id'];
            $sale = (float) ($channel['sale_price'] ?? $channel['price']);
            $state = $this->channelState($remote, $channelId);

            if ($channel['needs_mirakl_fallback'] ?? false) {
                $applied = $this->applyFallback($sale, $state);
                $row = [
                    'id' => $channelId,
                    'price' => $applied['price'],
                    'discount_price' => $applied['discount_price'],
                    'discount_start_date' => $applied['discount_start_date'],
                    'discount_end_date' => $applied['discount_end_date'],
                    'sale_price' => $sale,
                ];
            } elseif (($channel['needs_mirakl_dates'] ?? false) && ($channel['discount_price'] ?? null) !== null) {
                $row = [
                    'id' => $channelId,
                    'price' => (float) $channel['price'],
                    'discount_price' => (float) $channel['discount_price'],
                    'discount_start_date' => $state['discount_start_date'],
                    'discount_end_date' => $state['discount_end_date'],
                    'sale_price' => $sale,
                ];
            } else {
                $row = [
                    'id' => $channelId,
                    'price' => (float) $channel['price'],
                    'discount_price' => $channel['discount_price'] ?? null,
                    'discount_start_date' => $channel['discount_start_date'] ?? null,
                    'discount_end_date' => $channel['discount_end_date'] ?? null,
                    'sale_price' => $sale,
                ];
            }

            $channels[] = $row;
            $primaryPrice ??= $row['price'];
            $primaryDiscount ??= $row['discount_price'];
            $primarySale ??= $sale;
        }

        return new Offer(
            sku: $offer->sku,
            price: $primaryPrice ?? $offer->price,
            ean: $offer->ean,
            shopId: $offer->shopId,
            channels: $channels,
            discountPrice: $primaryDiscount,
            salePrice: $primarySale,
        );
    }

    /**
     * @param  array{origin: float|null, discount: float|null, discount_start_date: string|null, discount_end_date: string|null}  $state
     * @return array{price: float, discount_price: float|null, discount_start_date: string|null, discount_end_date: string|null}
     */
    private function applyFallback(float $sale, array $state): array
    {
        $origin = $state['origin'];
        $hasDiscount = $origin !== null
            && $state['discount'] !== null
            && $state['discount'] > 0
            && $state['discount'] < $origin;

        if ($hasDiscount && $sale < $origin) {
            return [
                'price' => $origin,
                'discount_price' => $sale,
                'discount_start_date' => $state['discount_start_date'],
                'discount_end_date' => $state['discount_end_date'],
            ];
        }

        return [
            'price' => $sale,
            'discount_price' => null,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $remote
     * @return array{origin: float|null, discount: float|null, discount_start_date: string|null, discount_end_date: string|null}
     */
    private function defaultState(?array $remote): array
    {
        return [
            'origin' => is_array($remote) ? ($remote['origin'] ?? null) : null,
            'discount' => is_array($remote) ? ($remote['discount'] ?? null) : null,
            'discount_start_date' => is_array($remote) ? ($remote['discount_start_date'] ?? null) : null,
            'discount_end_date' => is_array($remote) ? ($remote['discount_end_date'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $remote
     * @return array{origin: float|null, discount: float|null, discount_start_date: string|null, discount_end_date: string|null}
     */
    private function channelState(?array $remote, string $channelId): array
    {
        $channels = is_array($remote['channels'] ?? null) ? $remote['channels'] : [];
        if (isset($channels[$channelId]) && is_array($channels[$channelId])) {
            return [
                'origin' => $channels[$channelId]['origin'] ?? null,
                'discount' => $channels[$channelId]['discount'] ?? null,
                'discount_start_date' => $channels[$channelId]['discount_start_date'] ?? null,
                'discount_end_date' => $channels[$channelId]['discount_end_date'] ?? null,
            ];
        }

        return $this->defaultState($remote);
    }
}
