<?php

namespace Tests\Unit;

use App\Domain\Pricing\PriceResolver;
use App\Models\PriceColumn;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceResolverTest extends TestCase
{
    #[Test]
    public function it_prefers_overrides_over_row_values(): void
    {
        $column = new PriceColumn(['csv_column' => 'Prix_TTC']);
        $resolver = new PriceResolver;

        $price = $resolver->resolve(
            row: ['Prix_TTC' => '100.00'],
            mappedColumn: $column,
            overrides: ['Prix_TTC' => '149.90'],
        );

        $this->assertSame(149.90, $price);
    }

    #[Test]
    public function it_blocks_when_no_mapping_and_fallback_disabled(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PriceResolver)->resolve(
            row: ['Prix_TTC' => '10', 'Prix_TTC_Italie' => '20'],
            mappedColumn: null,
            allowMaxFallback: false,
        );
    }

    #[Test]
    public function it_can_use_legacy_max_fallback(): void
    {
        $price = (new PriceResolver)->resolve(
            row: ['Prix_TTC' => '10', 'Prix_TTC_Italie' => '20'],
            mappedColumn: null,
            allowMaxFallback: true,
        );

        $this->assertSame(20.0, $price);
    }
}
