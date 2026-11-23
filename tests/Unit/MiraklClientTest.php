<?php

namespace Tests\Unit;

use App\Domain\Offer\Offer;
use App\Enums\MarketplaceDriver;
use App\Models\Marketplace;
use App\Services\Marketplace\Mirakl\MiraklClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiraklClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_mono_push_posts_one_pri01_import(): void
    {
        Http::fake([
            'https://example.test/api/offers/pricing/imports*' => Http::response(['import_id' => 1], 200),
        ]);

        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => ['2861'],
            'is_active' => true,
        ]);

        $result = app(MiraklClient::class)->pushPrices($marketplace, [
            new Offer(sku: 'ABC1', price: 12.5, shopId: '2861'),
        ]);

        $this->assertTrue($result->success);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'shop_id=2861')
                && $request->hasHeader('Authorization', 'secret-key');
        });
    }

    public function test_multi_shop_push_posts_one_import_per_shop(): void
    {
        Http::fake([
            'https://example.test/api/offers/pricing/imports*' => Http::response(['import_id' => 1], 200),
        ]);

        $marketplace = Marketplace::query()->create([
            'name' => 'Bricodepot',
            'slug' => 'bricodepot',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => ['2102', '2227'],
            'is_active' => true,
        ]);

        $result = app(MiraklClient::class)->pushPrices($marketplace, [
            new Offer(sku: 'ABC1', price: 11, shopId: '2102'),
            new Offer(sku: 'ABC1', price: 13, shopId: '2227'),
        ]);

        $this->assertTrue($result->success);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'shop_id=2102'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'shop_id=2227'));
    }

    public function test_channel_push_includes_channels_column(): void
    {
        Http::fake([
            'https://example.test/api/offers/pricing/imports*' => Http::response(['import_id' => 1], 200),
        ]);

        $marketplace = Marketplace::query()->create([
            'name' => 'Leroy Merlin',
            'slug' => 'leroymerlin',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => ['2062'],
            'is_active' => true,
        ]);

        $result = app(MiraklClient::class)->pushPrices($marketplace, [
            new Offer(
                sku: 'ABC1',
                price: 12.5,
                shopId: '2062',
                channels: [
                    ['id' => 'FR', 'price' => 12.5],
                    ['id' => 'IT', 'price' => 14.0],
                ],
            ),
        ]);

        $this->assertTrue($result->success);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'shop_id=2062'));
    }
}
