<?php

namespace App\Services\Marketplace;

use App\Enums\MarketplaceDriver;
use App\Models\Marketplace;
use Illuminate\Support\Facades\Http;

final class MarketplaceConnectionTester
{
    /**
     * Lightweight auth / connectivity check for admin "Tester l’API".
     *
     * @return array{ok: bool, status: int|null, message: string}
     */
    public function test(Marketplace $marketplace): array
    {
        return match ($marketplace->driver) {
            MarketplaceDriver::Mirakl => $this->testMirakl($marketplace),
            MarketplaceDriver::Kaufland => $this->testKaufland($marketplace),
            MarketplaceDriver::Other => [
                'ok' => false,
                'status' => null,
                'message' => 'Driver « Autre » : aucun test API automatisé.',
            ],
        };
    }

    /**
     * @return array{ok: bool, status: int|null, message: string}
     */
    private function testMirakl(Marketplace $marketplace): array
    {
        $apiKey = $marketplace->api_key;
        if (! is_string($apiKey) || $apiKey === '') {
            return ['ok' => false, 'status' => null, 'message' => 'API key Mirakl manquante.'];
        }

        $base = rtrim((string) $marketplace->api_url, '/');
        if ($base === '') {
            return ['ok' => false, 'status' => null, 'message' => 'API URL manquante.'];
        }

        $endpoint = $base.'/account';
        $shopId = $marketplace->shop_ids[0] ?? null;
        if (is_string($shopId) && $shopId !== '') {
            $endpoint .= '?shop_id='.urlencode($shopId);
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($endpoint);
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'message' => 'Connexion impossible : '.$e->getMessage()];
        }

        if ($response->successful()) {
            $shopHint = $shopId ? " (shop {$shopId})" : '';

            return [
                'ok' => true,
                'status' => $response->status(),
                'message' => "OK — Mirakl /account{$shopHint} HTTP {$response->status()}",
            ];
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'message' => 'Échec Mirakl /account HTTP '.$response->status().' — '.$this->snippet($response->body()),
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, message: string}
     */
    private function testKaufland(Marketplace $marketplace): array
    {
        $clientKey = $marketplace->client_key;
        $clientSecret = $marketplace->client_secret;

        if (! is_string($clientKey) || $clientKey === '' || ! is_string($clientSecret) || $clientSecret === '') {
            return ['ok' => false, 'status' => null, 'message' => 'Client key / secret Kaufland manquants.'];
        }

        $base = rtrim((string) $marketplace->api_url, '/');
        if ($base === '') {
            return ['ok' => false, 'status' => null, 'message' => 'API URL manquante.'];
        }

        // Light read endpoint used as connectivity probe.
        $pathWithQuery = '/categories?limit=1';
        $method = 'GET';
        $body = '';
        $timestamp = (string) time();
        $stringToSign = "{$method}\n{$pathWithQuery}\n{$body}\n{$timestamp}";
        $signature = hash_hmac('sha256', $stringToSign, $clientSecret);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Shop-Client-Key' => $clientKey,
                    'Shop-Timestamp' => $timestamp,
                    'Shop-Signature' => $signature,
                ])
                ->get($base.$pathWithQuery);
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'message' => 'Connexion impossible : '.$e->getMessage()];
        }

        if ($response->successful()) {
            return [
                'ok' => true,
                'status' => $response->status(),
                'message' => "OK — Kaufland /categories HTTP {$response->status()}",
            ];
        }

        // Some shops reject categories but accept auth with 403/404 differently — still surface body.
        return [
            'ok' => false,
            'status' => $response->status(),
            'message' => 'Échec Kaufland /categories HTTP '.$response->status().' — '.$this->snippet($response->body()),
        ];
    }

    private function snippet(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        return mb_substr($body, 0, 180);
    }
}
