<?php

namespace App\Services\Marketplace\Mirakl;

use App\Domain\Offer\Offer;
use App\Models\Marketplace;
use App\Services\Marketplace\MarketplaceClientInterface;
use App\Services\Marketplace\MarketplacePushResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MiraklClient implements MarketplaceClientInterface
{
    public function __construct(
        private readonly Pri01Exporter $exporter,
        private readonly MiraklDiscountEnricher $discountEnricher,
    ) {}

    /**
     * @param  list<Offer>  $offers
     */
    public function pushPrices(Marketplace $marketplace, array $offers): MarketplacePushResult
    {
        if ($offers === []) {
            return new MarketplacePushResult(false, 'No offers to push.');
        }

        $apiKey = $marketplace->api_key;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException("Marketplace [{$marketplace->slug}] is missing an API key.");
        }

        $offers = $this->discountEnricher->enrich($marketplace, $offers);

        $byShop = [];
        foreach ($offers as $offer) {
            $shopId = $offer->shopId
                ?? ($marketplace->shop_ids[0] ?? null);
            if ($shopId === null || $shopId === '') {
                continue;
            }
            $byShop[(string) $shopId][] = $offer;
        }

        if ($byShop === []) {
            return new MarketplacePushResult(false, 'Aucune offre avec shop_id.');
        }

        $messages = [];
        $allOk = true;
        $lastStatus = null;
        $raw = [];

        foreach ($byShop as $shopId => $shopOffers) {
            $withChannels = $this->offersHaveChannels($shopOffers);
            $result = $this->postImport(
                marketplace: $marketplace,
                apiKey: $apiKey,
                offers: $shopOffers,
                withChannels: $withChannels,
                withShop: false,
                shopIdQuery: (string) $shopId,
                label: $marketplace->slug.'_shop_'.$shopId,
            );
            $lastStatus = $result->httpStatus;
            $raw[(string) $shopId] = $result->raw;
            $messages[] = "shop {$shopId}: ".($result->success ? 'OK ('.count($shopOffers).')' : $result->message);
            if (! $result->success) {
                $allOk = false;
            }
        }

        return new MarketplacePushResult(
            success: $allOk,
            message: implode(' | ', $messages),
            httpStatus: $lastStatus,
            raw: $raw,
        );
    }

    /**
     * @param  list<Offer>  $offers
     */
    private function offersHaveChannels(array $offers): bool
    {
        foreach ($offers as $offer) {
            if ($offer->channels !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Offer>  $offers
     */
    private function postImport(
        Marketplace $marketplace,
        string $apiKey,
        array $offers,
        bool $withChannels,
        bool $withShop,
        ?string $shopIdQuery,
        string $label,
    ): MarketplacePushResult {
        $csv = $this->exporter->export($offers, $withChannels, $withShop);

        $relativePath = 'mirakl/pri01_'.$label.'_'.now()->format('Ymd_His_u').'.csv';
        Storage::disk('local')->put($relativePath, $csv);
        $absolutePath = Storage::disk('local')->path($relativePath);

        $endpoint = rtrim($marketplace->api_url, '/').'/offers/pricing/imports';
        if ($shopIdQuery) {
            $endpoint .= '?shop_id='.urlencode($shopIdQuery);
        }

        $response = Http::withHeaders([
            'Authorization' => $apiKey,
            'Accept' => 'application/json',
        ])
            ->attach('file', (string) file_get_contents($absolutePath), basename($absolutePath))
            ->post($endpoint);

        return new MarketplacePushResult(
            success: $response->successful(),
            message: $response->successful()
                ? 'Mirakl pricing import accepted.'
                : 'Mirakl pricing import failed: '.$response->body(),
            httpStatus: $response->status(),
            raw: $response->json() ?? $response->body(),
        );
    }
}
