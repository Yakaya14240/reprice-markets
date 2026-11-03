<?php

namespace App\Services\Marketplace;

final readonly class MarketplacePushResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?int $httpStatus = null,
        public mixed $raw = null,
    ) {}
}
