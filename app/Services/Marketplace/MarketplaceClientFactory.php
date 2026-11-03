<?php

namespace App\Services\Marketplace;

use App\Enums\MarketplaceDriver;
use App\Models\Marketplace;
use App\Services\Marketplace\Kaufland\KauflandClient;
use App\Services\Marketplace\Mirakl\MiraklClient;
use InvalidArgumentException;

final class MarketplaceClientFactory
{
    public function __construct(
        private readonly MiraklClient $miraklClient,
        private readonly KauflandClient $kauflandClient,
    ) {}

    public function make(Marketplace $marketplace): MarketplaceClientInterface
    {
        return match ($marketplace->driver) {
            MarketplaceDriver::Mirakl => $this->miraklClient,
            MarketplaceDriver::Kaufland => $this->kauflandClient,
            default => throw new InvalidArgumentException("Unsupported marketplace driver [{$marketplace->driver->value}]."),
        };
    }
}
