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
    public function __construct(private readonly Pri01Exporter $exporter) {}

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

        $withChannels = $marketplace->supports_channels;
        $withShop = $marketplace->supports_multi_shop;
        $csv = $this->exporter->export($offers, $withChannels, $withShop);

        $relativePath = 'mirakl/pri01_'.$marketplace->slug.'_'.now()->format('Ymd_His').'.csv';
        Storage::disk('local')->put($relativePath, $csv);
        $absolutePath = Storage::disk('local')->path($relativePath);

        $shopId = $offers[0]->shopId
            ?? ($marketplace->shop_ids[0] ?? null);

        $endpoint = rtrim($marketplace->api_url, '/').'/offers/pricing/imports';
        if ($shopId) {
            $endpoint .= '?shop_id='.urlencode((string) $shopId);
        }

        $response = Http::withHeaders([
            'Authorization' => $apiKey,
            'Accept' => 'application/json',
        ])
            ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
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
