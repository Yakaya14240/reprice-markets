<?php

namespace App\Enums;

enum MarketplaceDriver: string
{
    case Mirakl = 'mirakl';
    case Kaufland = 'kaufland';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mirakl => 'Mirakl',
            self::Kaufland => 'Kaufland',
            self::Other => 'Autre',
        };
    }
}
