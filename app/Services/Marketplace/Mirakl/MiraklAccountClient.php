<?php

namespace App\Services\Marketplace\Mirakl;

use App\Models\Marketplace;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Lightweight Mirakl account helpers (A01 — GET /account).
 * Seller API keys often cannot call S20 /shops (403) but /account still returns their shop_id.
 */
final class MiraklAccountClient
{
    /**
     * @return array{id: string, name: string, state: string|null, raw: array<string, mixed>}
     */
    public function fetchDefaultShop(Marketplace $marketplace): array
    {
        [$apiKey, $base] = $this->credentials($marketplace);

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get($base.'/account');

        if (! $response->successful()) {
            throw new RuntimeException(
                'Mirakl /account HTTP '.$response->status().' — '.mb_substr(trim($response->body()), 0, 180)
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Mirakl /account : réponse invalide.');
        }

        $id = $payload['shop_id'] ?? $payload['shopId'] ?? null;
        if ($id === null || $id === '') {
            throw new RuntimeException('Mirakl /account ne contient pas de shop_id.');
        }

        return [
            'id' => (string) $id,
            'name' => (string) ($payload['shop_name'] ?? $payload['shopName'] ?? ('Shop '.$id)),
            'state' => isset($payload['shop_state']) ? (string) $payload['shop_state'] : null,
            'raw' => $payload,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(Marketplace $marketplace): array
    {
        $apiKey = $marketplace->api_key;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('API key Mirakl manquante.');
        }

        $base = rtrim((string) $marketplace->api_url, '/');
        if ($base === '') {
            throw new RuntimeException('API URL manquante.');
        }

        return [$apiKey, $base];
    }
}
