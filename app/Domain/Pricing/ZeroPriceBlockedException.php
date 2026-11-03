<?php

namespace App\Domain\Pricing;

use RuntimeException;

final class ZeroPriceBlockedException extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('Push blocked: one or more offers have a price <= 0.');
    }
}
