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
}
