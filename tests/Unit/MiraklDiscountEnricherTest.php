<?php

namespace Tests\Unit;

use App\Domain\Offer\Offer;
use App\Enums\MarketplaceDriver;
use App\Models\Marketplace;
use App\Services\Marketplace\Mirakl\MiraklDiscountEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MiraklDiscountEnricherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fallback_updates_discount_when_sale_below_mirakl_origin(): void
    {
        Http::fake([
            'https://example.test/api/offers*' => Http::response([
                'offers' => [[
                    'shop_sku' => 'ABC1',
                    'all_prices' => [[
                        'channel_code' => null,
                        'unit_origin_price' => 2015,
                        'unit_discount_price' => 978.99,
                        'discount_start_date' => '2026-01-01T00:00:00Z',
                        'discount_end_date' => '2026-12-31T23:59:59Z',
                    ]],
                ]],
            ], 200),
        ]);

        $marketplace = $this->marketplace();
        $offers = app(MiraklDiscountEnricher::class)->enrich($marketplace, [
            new Offer(
                sku: 'ABC1',
                price: 1000,
                shopId: '2861',
                salePrice: 1000,
                needsMiraklFallback: true,
            ),
        ]);

        $this->assertSame(2015.0, $offers[0]->price);
        $this->assertSame(1000.0, $offers[0]->discountPrice);
        $this->assertSame('2026-01-01T00:00:00Z', $offers[0]->discountStartDate);
        $this->assertSame('2026-12-31T23:59:59Z', $offers[0]->discountEndDate);
    }

    #[Test]
    public function fallback_replaces_origin_when_sale_above_mirakl_origin(): void
    {
        Http::fake([
            'https://example.test/api/offers*' => Http::response([
                'offers' => [[
                    'shop_sku' => 'ABC1',
                    'all_prices' => [[
                        'unit_origin_price' => 1000,
                        'unit_discount_price' => 800,
                        'discount_start_date' => '2026-01-01T00:00:00Z',
                        'discount_end_date' => '2026-12-31T23:59:59Z',
                    ]],
                ]],
            ], 200),
        ]);

        $marketplace = $this->marketplace();
        $offers = app(MiraklDiscountEnricher::class)->enrich($marketplace, [
            new Offer(
                sku: 'ABC1',
                price: 1200,
                shopId: '2861',
                salePrice: 1200,
                needsMiraklFallback: true,
            ),
        ]);

        $this->assertSame(1200.0, $offers[0]->price);
        $this->assertNull($offers[0]->discountPrice);
        $this->assertNull($offers[0]->discountStartDate);
    }

    #[Test]
    public function csv_discount_reuses_mirakl_dates(): void
    {
        Http::fake([
            'https://example.test/api/offers*' => Http::response([
                'offers' => [[
                    'shop_sku' => 'ABC1',
                    'all_prices' => [[
                        'unit_origin_price' => 2015,
                        'unit_discount_price' => 978.99,
                        'discount_start_date' => '2026-03-01T00:00:00Z',
                        'discount_end_date' => '2026-06-30T23:59:59Z',
                    ]],
                ]],
            ], 200),
        ]);

        $marketplace = $this->marketplace();
        $offers = app(MiraklDiscountEnricher::class)->enrich($marketplace, [
            new Offer(
                sku: 'ABC1',
                price: 2015,
                shopId: '2861',
                discountPrice: 900,
                salePrice: 900,
                needsMiraklDates: true,
            ),
        ]);

        $this->assertSame(2015.0, $offers[0]->price);
        $this->assertSame(900.0, $offers[0]->discountPrice);
        $this->assertSame('2026-03-01T00:00:00Z', $offers[0]->discountStartDate);
        $this->assertSame('2026-06-30T23:59:59Z', $offers[0]->discountEndDate);
    }

    private function marketplace(): Marketplace
    {
        return Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-discount',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => ['2861'],
            'is_active' => true,
        ]);
    }
}
