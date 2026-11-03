<?php

namespace App\Services\Marketplace\Kaufland;

use App\Domain\Offer\Offer;
use App\Models\Marketplace;
use App\Services\Marketplace\MarketplaceClientInterface;
use App\Services\Marketplace\MarketplacePushResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kaufland Seller API v2 client.
 * Signature: HMAC-SHA256(timestamp + jsonBody, client_secret) — confirm against live credentials before prod.
 */
final class KauflandClient implements MarketplaceClientInterface
{
    /**
     * @param  list<Offer>  $offers
     */
    public function pushPrices(Marketplace $marketplace, array $offers): MarketplacePushResult
    {
        $clientKey = $marketplace->client_key;
        $clientSecret = $marketplace->client_secret;

        if (! is_string($clientKey) || $clientKey === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException("Marketplace [{$marketplace->slug}] is missing Kaufland client credentials.");
        }

        $results = [];
        $allOk = true;

        foreach ($offers as $offer) {
            if (! $offer->ean) {
                $allOk = false;
                $results[] = "SKU {$offer->sku}: missing EAN";
                continue;
            }

            $payload = [
                'ean' => [$offer->ean],
                'attributes' => [
                    'list_price' => [
                        'simple_values' => [
                            ['value' => number_format($offer->price, 2, '.', '')],
                        ],
                    ],
                ],
            ];

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $timestamp = (string) time();
            $signature = hash_hmac('sha256', $timestamp.$json, $clientSecret);

            $endpoint = rtrim($marketplace->api_url, '/').'/product-data';

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Shop-Client-Key' => $clientKey,
                'Shop-Timestamp' => $timestamp,
                'Shop-Signature' => $signature,
            ])->withBody($json, 'application/json')->put($endpoint);

            if (! $response->successful()) {
                $allOk = false;
                $results[] = "EAN {$offer->ean}: HTTP {$response->status()} ".$response->body();
            } else {
                $results[] = "EAN {$offer->ean}: ok";
            }
        }

        return new MarketplacePushResult(
            success: $allOk,
            message: implode(' | ', $results),
            raw: $results,
        );
    }
}
