<?php

namespace Tests\Unit;

use App\Domain\Offer\Offer;
use App\Services\Marketplace\Mirakl\Pri01Exporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Pri01ExporterTest extends TestCase
{
    #[Test]
    public function it_exports_standard_pri01(): void
    {
        $csv = (new Pri01Exporter)->export([
            new Offer(sku: 'SKU-1', price: 99.9),
        ]);

        $this->assertStringContainsString('offer-sku;price;discount-price;discount-start-date;discount-end-date', $csv);
        $this->assertStringContainsString('SKU-1;99.90;;;', $csv);
    }

    #[Test]
    public function it_exports_multichannel_rows(): void
    {
        $csv = (new Pri01Exporter)->export([
            new Offer(
                sku: 'SKU-1',
                price: 100,
                channels: [
                    ['id' => 'FR', 'price' => 100],
                    ['id' => 'IT', 'price' => 110],
                ],
            ),
        ], withChannels: true);

        $this->assertStringContainsString('channels', $csv);
        $this->assertStringContainsString('SKU-1;100.00;;;;FR', $csv);
        $this->assertStringContainsString('SKU-1;110.00;;;;IT', $csv);
    }

    #[Test]
    public function it_exports_per_channel_discount_prices(): void
    {
        $csv = (new Pri01Exporter)->export([
            new Offer(
                sku: 'SKU-1',
                price: 2015,
                channels: [
                    [
                        'id' => 'FR',
                        'price' => 2015,
                        'discount_price' => 978.99,
                        'discount_start_date' => '2026-01-01T00:00:00Z',
                        'discount_end_date' => '2026-12-31T23:59:59Z',
                    ],
                    [
                        'id' => 'IT',
                        'price' => 1100,
                        'discount_price' => null,
                    ],
                ],
            ),
        ], withChannels: true);

        $this->assertStringContainsString('SKU-1;2015.00;978.99;2026-01-01T00:00:00Z;2026-12-31T23:59:59Z;FR', $csv);
        $this->assertStringContainsString('SKU-1;1100.00;;;;IT', $csv);
    }
}
