<?php

namespace App\Services\Marketplace\Mirakl;

use App\Models\Marketplace;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read current Mirakl offer pricing (OF21 — GET /offers) for discount fallback / date reuse.
 */
final class MiraklOfferPricingReader
{
    /**
     * @param  list<string>  $skus
     * @return array<string, array{
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
     * }>
     */
    public function pricingBySku(Marketplace $marketplace, string $shopId, array $skus): array
    {
        $skus = array_values(array_unique(array_filter($skus)));
        if ($skus === []) {
            return [];
        }

        $apiKey = $marketplace->api_key;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException("Marketplace [{$marketplace->slug}] is missing an API key.");
        }

        $base = rtrim((string) $marketplace->api_url, '/');
        $result = [];

        foreach (array_chunk($skus, 50) as $chunk) {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($base.'/offers', [
                    'shop_id' => $shopId,
                    'sku' => implode(',', $chunk),
                    'max' => 100,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Mirakl /offers HTTP '.$response->status().' — '.mb_substr(trim($response->body()), 0, 180)
                );
            }

            $payload = $response->json();
            $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];

            foreach ($offers as $offer) {
                if (! is_array($offer)) {
                    continue;
                }
                $sku = (string) ($offer['shop_sku'] ?? $offer['sku'] ?? '');
                if ($sku === '') {
                    continue;
                }
                $result[$sku] = $this->parseOfferPricing($offer);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array{
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
     * }
     */
    private function parseOfferPricing(array $offer): array
    {
        $channels = [];
        $default = [
            'origin' => null,
            'discount' => null,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ];

        $allPrices = is_array($offer['all_prices'] ?? null) ? $offer['all_prices'] : [];
        foreach ($allPrices as $priceRow) {
            if (! is_array($priceRow)) {
                continue;
            }
            $parsed = [
                'origin' => $this->toFloat($priceRow['unit_origin_price'] ?? $priceRow['origin_price'] ?? null),
                'discount' => $this->toFloat($priceRow['unit_discount_price'] ?? $priceRow['discount_price'] ?? null),
                'discount_start_date' => $this->toDateString($priceRow['discount_start_date'] ?? null),
                'discount_end_date' => $this->toDateString($priceRow['discount_end_date'] ?? null),
            ];

            $channelCode = $priceRow['channel_code'] ?? null;
            if (is_string($channelCode) && $channelCode !== '') {
                $channels[$channelCode] = $parsed;
            } else {
                $default = $parsed;
            }
        }

        if ($default['origin'] === null && isset($offer['discount']) && is_array($offer['discount'])) {
            $default['origin'] = $this->toFloat($offer['discount']['origin_price'] ?? $offer['price'] ?? null);
            $default['discount'] = $this->toFloat($offer['discount']['discount_price'] ?? null);
            $default['discount_start_date'] = $this->toDateString($offer['discount']['start_date'] ?? null);
            $default['discount_end_date'] = $this->toDateString($offer['discount']['end_date'] ?? null);
        }

        if ($default['origin'] === null) {
            $default['origin'] = $this->toFloat($offer['price'] ?? null);
        }

        return [
            'origin' => $default['origin'],
            'discount' => $default['discount'],
            'discount_start_date' => $default['discount_start_date'],
            'discount_end_date' => $default['discount_end_date'],
            'channels' => $channels,
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function toDateString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
