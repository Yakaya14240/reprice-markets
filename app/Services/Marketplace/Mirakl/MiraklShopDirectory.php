<?php

namespace App\Services\Marketplace\Mirakl;

use App\Enums\MarketplaceDriver;
use App\Models\Marketplace;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MiraklShopDirectory
{
    public function __construct(private readonly MiraklAccountClient $account) {}

    /**
     * Fetch shops for this Mirakl API key.
     * Prefers S20 GET /shops ; on 403/401 falls back to A01 GET /account (single default shop).
     *
     * @return list<array{id: string, name: string, state: string|null}>
     */
    public function listShops(Marketplace $marketplace): array
    {
        if ($marketplace->driver !== MarketplaceDriver::Mirakl) {
            throw new RuntimeException('La liste des shops n’est disponible que pour Mirakl.');
        }

        $apiKey = $marketplace->api_key;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('API key Mirakl manquante.');
        }

        $base = rtrim((string) $marketplace->api_url, '/');
        if ($base === '') {
            throw new RuntimeException('API URL manquante.');
        }

        try {
            $shops = $this->listViaShopsEndpoint($apiKey, $base);
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'HTTP 403') && ! str_contains($e->getMessage(), 'HTTP 401')) {
                throw $e;
            }

            // Seller keys often cannot list all shops — recover the default shop from /account.
            $default = $this->account->fetchDefaultShop($marketplace);
            $shops = [[
                'id' => $default['id'],
                'name' => $default['name'].' (compte)',
                'state' => $default['state'],
            ]];
        }

        // Do not persist shop_ids here — the admin form owns persistence via « Enregistrer ».

        return $shops;
    }

    /**
     * @return list<array{id: string, name: string, state: string|null}>
     */
    private function listViaShopsEndpoint(string $apiKey, string $base): array
    {
        $shops = [];
        $offset = 0;
        $max = 100;

        do {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($base.'/shops', [
                    'paginate' => 'true',
                    'max' => $max,
                    'offset' => $offset,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Mirakl /shops HTTP '.$response->status().' — '.mb_substr(trim($response->body()), 0, 180)
                );
            }

            $payload = $response->json();
            $batch = is_array($payload['shops'] ?? null) ? $payload['shops'] : [];
            foreach ($batch as $shop) {
                if (! is_array($shop)) {
                    continue;
                }
                $id = $shop['shop_id'] ?? $shop['id'] ?? null;
                if ($id === null || $id === '') {
                    continue;
                }
                $shops[] = [
                    'id' => (string) $id,
                    'name' => (string) ($shop['shop_name'] ?? $shop['name'] ?? ('Shop '.$id)),
                    'state' => isset($shop['shop_state']) ? (string) $shop['shop_state'] : null,
                ];
            }

            $total = (int) ($payload['total_count'] ?? count($batch));
            $offset += count($batch);
            $hasMore = count($batch) >= $max && $offset < $total;
        } while ($hasMore);

        usort($shops, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $shops;
    }
}
