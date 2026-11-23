<?php

namespace App\Services\Marketplace;

use App\Domain\Offer\Offer;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\MarketplacePriceMapping;
use App\Models\PendingPriceChange;
use App\Models\PriceColumn;
use App\Services\Feed\LengowCsvParser;
use Illuminate\Support\Collection;

final class OfferAssembler
{
    public function __construct(
        private readonly LengowCsvParser $parser,
        private readonly DiscountPricingDecider $discountDecider,
    ) {}

    /**
     * Build Mirakl (or generic) offers for one marketplace from post-sync product state.
     *
     * @param  Collection<int, PendingPriceChange>  $pending
     * @param  list<string>  $headers
     * @param  list<array<string, string|null>>  $products
     * @return list<Offer>
     */
    public function offersFor(
        Marketplace $marketplace,
        FeedFile $feed,
        Collection $pending,
        array $headers,
        array $products,
    ): array {
        $marketplace->loadMissing(['priceMappings.priceColumn']);

        $skus = $pending->pluck('sku')->unique()->filter()->values()->all();
        if ($skus === []) {
            return [];
        }

        $bySku = [];
        foreach ($products as $product) {
            $sku = $feed->productSku($product, $headers);
            if ($sku !== '' && in_array($sku, $skus, true)) {
                $bySku[$sku] = $product;
            }
        }

        $mappings = $marketplace->priceMappings
            ->filter(fn (MarketplacePriceMapping $m) => filled($m->shop_id) && filled($m->priceColumn?->csv_column));

        if ($mappings->isEmpty()) {
            return [];
        }

        $publicColumn = PriceColumn::query()
            ->where('feed_file_id', $feed->id)
            ->where('is_public_price', true)
            ->where('is_active', true)
            ->first();

        /** @var Collection<string, Collection<int, MarketplacePriceMapping>> $byShop */
        $byShop = $mappings->groupBy(fn (MarketplacePriceMapping $m) => (string) $m->shop_id);

        $offers = [];
        foreach ($bySku as $sku => $product) {
            $publicPrice = $this->publicPriceFromProduct($product, $publicColumn);

            foreach ($byShop as $shopId => $shopMappings) {
                $offer = $this->assembleShopOffer(
                    (string) $sku,
                    $product,
                    (string) $shopId,
                    $shopMappings,
                    $publicPrice,
                    $publicColumn,
                );
                if ($offer !== null) {
                    $offers[] = $offer;
                }
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, string|null>  $product
     */
    private function publicPriceFromProduct(array $product, ?PriceColumn $publicColumn): ?float
    {
        if ($publicColumn === null || ! filled($publicColumn->csv_column)) {
            return null;
        }

        return $this->parsePrice($product[$publicColumn->csv_column] ?? null);
    }

    /**
     * @param  Collection<int, MarketplacePriceMapping>  $shopMappings
     * @param  array<string, string|null>  $product
     */
    private function assembleShopOffer(
        string $sku,
        array $product,
        string $shopId,
        Collection $shopMappings,
        ?float $publicPrice,
        ?PriceColumn $publicColumn,
    ): ?Offer {
        $channelMappings = $shopMappings->filter(fn (MarketplacePriceMapping $m) => $m->hasChannel());

        if ($channelMappings->isNotEmpty()) {
            $channels = [];
            $primaryPrice = null;
            $primaryDiscount = null;
            $offerNeedsFallback = false;
            $offerNeedsDates = false;
            $primarySale = null;

            foreach ($channelMappings as $mapping) {
                $csvColumn = (string) $mapping->priceColumn->csv_column;
                $salePrice = $this->parsePrice($product[$csvColumn] ?? null);
                if ($salePrice === null || $salePrice <= 0) {
                    continue;
                }

                $decision = $this->discountDecider->decide(
                    $publicPrice,
                    $salePrice,
                    $this->sameCurrency($publicColumn, $mapping->priceColumn),
                );
                $channels[] = [
                    'id' => (string) $mapping->channel_code,
                    'price' => $decision->unitPrice,
                    'discount_price' => $decision->discountPrice,
                    'sale_price' => $decision->salePrice,
                    'needs_mirakl_fallback' => $decision->needsMiraklFallback,
                    'needs_mirakl_dates' => $decision->needsMiraklDates,
                ];
                $primaryPrice ??= $decision->unitPrice;
                $primaryDiscount ??= $decision->discountPrice;
                $primarySale ??= $decision->salePrice;
                $offerNeedsFallback = $offerNeedsFallback || $decision->needsMiraklFallback;
                $offerNeedsDates = $offerNeedsDates || $decision->needsMiraklDates;
            }

            if ($channels === [] || $primaryPrice === null) {
                return null;
            }

            return new Offer(
                sku: $sku,
                price: $primaryPrice,
                ean: $this->parser->extractEan($product),
                shopId: $shopId,
                channels: $channels,
                discountPrice: $primaryDiscount,
                salePrice: $primarySale,
                needsMiraklFallback: $offerNeedsFallback,
                needsMiraklDates: $offerNeedsDates,
            );
        }

        $mapping = $shopMappings->first();
        if ($mapping === null) {
            return null;
        }

        $csvColumn = (string) $mapping->priceColumn->csv_column;
        $salePrice = $this->parsePrice($product[$csvColumn] ?? null);
        if ($salePrice === null || $salePrice <= 0) {
            return null;
        }

        $decision = $this->discountDecider->decide(
            $publicPrice,
            $salePrice,
            $this->sameCurrency($publicColumn, $mapping->priceColumn),
        );

        return new Offer(
            sku: $sku,
            price: $decision->unitPrice,
            ean: $this->parser->extractEan($product),
            shopId: $shopId,
            discountPrice: $decision->discountPrice,
            salePrice: $decision->salePrice,
            needsMiraklFallback: $decision->needsMiraklFallback,
            needsMiraklDates: $decision->needsMiraklDates,
        );
    }

    private function sameCurrency(?PriceColumn $publicColumn, ?PriceColumn $saleColumn): bool
    {
        if ($publicColumn === null || $saleColumn === null) {
            return true;
        }

        $publicCurrencyId = $publicColumn->currency_id;
        $saleCurrencyId = $saleColumn->currency_id;

        if ($publicCurrencyId === null || $saleCurrencyId === null) {
            return true;
        }

        return (int) $publicCurrencyId === (int) $saleCurrencyId;
    }

    private function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
