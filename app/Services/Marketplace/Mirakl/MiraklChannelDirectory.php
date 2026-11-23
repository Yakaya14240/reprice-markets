<?php

namespace App\Services\Marketplace\Mirakl;

use App\Enums\MarketplaceDriver;
use App\Models\Marketplace;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MiraklChannelDirectory
{
    /**
     * Fetch enabled channels for a given Mirakl shop (CH11 — GET /channels?shop_id=).
     *
     * @return list<array{code: string, label: string, description: string|null, display: string}>
     */
    public function listChannels(Marketplace $marketplace, string $shopId): array
    {
        if ($marketplace->driver !== MarketplaceDriver::Mirakl) {
            throw new RuntimeException('La liste des channels n’est disponible que pour Mirakl.');
        }

        $shopId = trim($shopId);
        if ($shopId === '') {
            throw new RuntimeException('Un shop_id est requis pour charger les channels.');
        }

        $apiKey = $marketplace->api_key;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('API key Mirakl manquante.');
        }

        $base = rtrim((string) $marketplace->api_url, '/');
        if ($base === '') {
            throw new RuntimeException('API URL manquante.');
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get($base.'/channels', ['shop_id' => $shopId]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Mirakl /channels HTTP '.$response->status().' — '.mb_substr(trim($response->body()), 0, 180)
            );
        }

        $payload = $response->json();
        $batch = is_array($payload['channels'] ?? null) ? $payload['channels'] : [];
        $channels = [];

        foreach ($batch as $channel) {
            if (! is_array($channel)) {
                continue;
            }
            $code = $channel['code'] ?? null;
            if ($code === null || $code === '') {
                continue;
            }
            $label = (string) ($channel['label'] ?? $channel['description'] ?? $code);
            $channels[] = [
                'code' => (string) $code,
                'label' => $label,
                'description' => isset($channel['description']) ? (string) $channel['description'] : null,
                'display' => "{$shopId} — {$label} ({$code})",
            ];
        }

        usort($channels, static fn (array $a, array $b): int => strnatcasecmp($a['code'], $b['code']));

        return $channels;
    }
}
