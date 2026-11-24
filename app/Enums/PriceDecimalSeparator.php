<?php

namespace App\Enums;

enum PriceDecimalSeparator: string
{
    case Dot = '.';
    case Comma = ',';

    public function label(): string
    {
        return match ($this) {
            self::Dot => 'Point (12.50)',
            self::Comma => 'Virgule (12,50)',
        };
    }
}
