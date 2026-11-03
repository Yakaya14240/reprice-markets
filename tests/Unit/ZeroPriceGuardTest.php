<?php

namespace Tests\Unit;

use App\Domain\Offer\Offer;
use App\Domain\Pricing\ZeroPriceBlockedException;
use App\Domain\Pricing\ZeroPriceGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ZeroPriceGuardTest extends TestCase
{
    #[Test]
    public function it_blocks_zero_prices(): void
    {
        $this->expectException(ZeroPriceBlockedException::class);

        (new ZeroPriceGuard)->assertNoZeroPrices([
            new Offer(sku: 'A', price: 0),
        ]);
    }

    #[Test]
    public function it_allows_positive_prices(): void
    {
        (new ZeroPriceGuard)->assertNoZeroPrices([
            new Offer(sku: 'A', price: 12.5),
        ]);

        $this->assertTrue(true);
    }
}
