<?php

namespace App\Support;

use App\Enums\PriceDecimalSeparator;

final class PriceDecimalFormatter
{
    public function format(float|int|string $value, PriceDecimalSeparator $separator, int $decimals = 2): string
    {
        $amount = $this->parse($value);
        if ($amount === null) {
            return '';
        }

        return number_format($amount, $decimals, $separator->value, '');
    }

    public function parse(float|int|string|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);
        $normalized = str_replace([' ', "\u{00A0}"], '', $normalized);

        if ($normalized === '') {
            return null;
        }

        // European "1.234,56" or plain "12,50" / "12.50"
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * Canonical string for FO number inputs (always dot decimal).
     */
    public function forInput(float|int|string|null $value, int $decimals = 2): ?string
    {
        $amount = $this->parse($value);
        if ($amount === null) {
            return null;
        }

        return number_format($amount, $decimals, '.', '');
    }
}
