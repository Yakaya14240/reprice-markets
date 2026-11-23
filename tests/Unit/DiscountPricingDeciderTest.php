<?php

namespace Tests\Unit;

use App\Services\Marketplace\DiscountPricingDecider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscountPricingDeciderTest extends TestCase
{
    #[Test]
    public function sale_below_public_creates_discount(): void
    {
        $decision = (new DiscountPricingDecider)->decide(2015.0, 978.99);

        $this->assertSame(2015.0, $decision->unitPrice);
        $this->assertSame(978.99, $decision->discountPrice);
        $this->assertFalse($decision->needsMiraklFallback);
        $this->assertTrue($decision->needsMiraklDates);
    }

    #[Test]
    public function sale_at_or_above_public_clears_discount(): void
    {
        $decision = (new DiscountPricingDecider)->decide(1000.0, 1000.0);

        $this->assertSame(1000.0, $decision->unitPrice);
        $this->assertNull($decision->discountPrice);
        $this->assertFalse($decision->needsMiraklFallback);
        $this->assertFalse($decision->needsMiraklDates);

        $higher = (new DiscountPricingDecider)->decide(1000.0, 1200.0);
        $this->assertSame(1200.0, $higher->unitPrice);
        $this->assertNull($higher->discountPrice);
    }

    #[Test]
    public function missing_public_price_flags_mirakl_fallback(): void
    {
        $decision = (new DiscountPricingDecider)->decide(null, 1000.0);

        $this->assertSame(1000.0, $decision->unitPrice);
        $this->assertNull($decision->discountPrice);
        $this->assertTrue($decision->needsMiraklFallback);
        $this->assertFalse($decision->needsMiraklDates);
    }

    #[Test]
    public function different_currencies_never_create_discount(): void
    {
        $decision = (new DiscountPricingDecider)->decide(2015.0, 900.0, sameCurrency: false);

        $this->assertSame(900.0, $decision->unitPrice);
        $this->assertNull($decision->discountPrice);
        $this->assertFalse($decision->needsMiraklFallback);
        $this->assertFalse($decision->needsMiraklDates);
    }
}
