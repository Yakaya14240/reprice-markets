<?php

namespace App\Domain\Pricing;

use App\Models\PriceColumn;
use InvalidArgumentException;

final class PriceResolver
{
    /**
     * Resolve the price to push for a product row.
     *
     * Priority:
     * 1. Explicit override (operator edit)
     * 2. Mapped price column
     * 3. Optional fallback among candidate "prix" columns (disabled by default)
     *
     * @param  array<string, string|null>  $row  Associative CSV row (header => value)
     * @param  array<string, float|string>  $overrides  csv_column => new price
     */
    public function resolve(
        array $row,
        ?PriceColumn $mappedColumn = null,
        array $overrides = [],
        bool $allowMaxFallback = false,
    ): float {
        if ($mappedColumn !== null) {
            $column = $mappedColumn->csv_column;

            if (array_key_exists($column, $overrides) && $overrides[$column] !== '' && $overrides[$column] !== null) {
                return $this->toFloat($overrides[$column]);
            }

            if (array_key_exists($column, $row) && $row[$column] !== '' && $row[$column] !== null) {
                return $this->toFloat($row[$column]);
            }
        }

        if ($allowMaxFallback) {
            $candidates = [];
            foreach ($row as $header => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if (stripos((string) $header, 'prix') !== false) {
                    $candidates[] = $this->toFloat($value);
                }
            }

            if ($candidates !== []) {
                return max($candidates);
            }
        }

        throw new InvalidArgumentException('Unable to resolve a price for the given product row.');
    }

    private function toFloat(float|int|string $value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], ['.', ''], trim($value));
        }

        return (float) $value;
    }
}
