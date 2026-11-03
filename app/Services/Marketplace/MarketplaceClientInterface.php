<?php

namespace App\Services\Marketplace;

use App\Domain\Offer\Offer;
use App\Models\Marketplace;

interface MarketplaceClientInterface
{
    /**
     * @param  list<Offer>  $offers
     */
    public function pushPrices(Marketplace $marketplace, array $offers): MarketplacePushResult;
}
