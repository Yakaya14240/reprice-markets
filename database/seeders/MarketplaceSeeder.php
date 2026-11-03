<?php

namespace Database\Seeders;

use App\Enums\MarketplaceDriver;
use App\Models\ChannelPriceMapping;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\MarketplaceShopPrice;
use App\Models\PriceColumn;
use Illuminate\Database\Seeder;

/**
 * Seeds non-secret reference data extracted from the legacy tool.
 * API keys / SFTP passwords must be provided via admin UI or .env — never committed.
 */
class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $feed = FeedFile::query()->updateOrCreate(
            ['remote_path' => '/test_clement/Flux_Lengow_Sun_CDISCOUNT.csv'],
            [
                'name' => 'Flux Lengow Sun Cdiscount',
                'delimiter' => "\t",
                'is_active' => true,
            ],
        );

        $columns = [
            ['name' => 'Prix France', 'csv_column' => 'Prix_TTC', 'alias' => 'FR'],
            ['name' => 'Prix Italie', 'csv_column' => 'Prix_TTC_Italie', 'alias' => 'IT'],
            ['name' => 'Prix Espagne', 'csv_column' => 'Prix_TTC_Espagne', 'alias' => 'ES'],
            ['name' => 'Prix Allemagne', 'csv_column' => 'Prix_TTC_Allemand', 'alias' => 'DE'],
            ['name' => 'Prix Pays-Bas', 'csv_column' => 'Prix_TTC_NL', 'alias' => 'NL'],
            ['name' => 'Prix Portugal', 'csv_column' => 'Prix_TTC_Portugal', 'alias' => 'PT'],
            ['name' => 'Prix BUT', 'csv_column' => 'Prix_BUT', 'alias' => 'BUT'],
        ];

        $priceColumns = [];
        foreach ($columns as $column) {
            $priceColumns[$column['alias']] = PriceColumn::query()->updateOrCreate(
                [
                    'feed_file_id' => $feed->id,
                    'csv_column' => $column['csv_column'],
                ],
                [
                    'name' => $column['name'],
                    'alias' => $column['alias'],
                    'is_active' => true,
                ],
            );
        }

        $leclerc = Marketplace::query()->updateOrCreate(
            ['slug' => 'leclerc'],
            [
                'name' => 'Leclerc',
                'driver' => MarketplaceDriver::Mirakl,
                'api_url' => 'https://leclerc-prod.mirakl.net/api',
                'shop_ids' => ['2861'],
                'supports_channels' => false,
                'supports_multi_shop' => false,
                'is_active' => true,
            ],
        );

        $leroymerlin = Marketplace::query()->updateOrCreate(
            ['slug' => 'leroymerlin'],
            [
                'name' => 'Leroy Merlin',
                'driver' => MarketplaceDriver::Mirakl,
                'api_url' => 'https://adeo-marketplace.mirakl.net/api',
                'shop_ids' => ['2062'],
                'supports_channels' => true,
                'supports_multi_shop' => false,
                'is_active' => true,
            ],
        );

        $bricodepot = Marketplace::query()->updateOrCreate(
            ['slug' => 'bricodepot'],
            [
                'name' => 'Bricodepot',
                'driver' => MarketplaceDriver::Mirakl,
                'api_url' => 'https://marketplace.bricodepot.es/api',
                'shop_ids' => ['2102', '2227'],
                'supports_channels' => false,
                'supports_multi_shop' => true,
                'is_active' => true,
            ],
        );

        Marketplace::query()->updateOrCreate(
            ['slug' => 'kaufland'],
            [
                'name' => 'Kaufland',
                'driver' => MarketplaceDriver::Kaufland,
                'api_url' => 'https://sellerapi.kaufland.com/v2',
                'shop_ids' => [],
                'supports_channels' => false,
                'supports_multi_shop' => false,
                'is_active' => true,
            ],
        );

        // Leroy Merlin multi-country channel → price column mapping
        $lmChannels = [
            'FR' => 'FR',
            'IT' => 'IT',
            'ES' => 'ES',
            'DE' => 'DE',
            'NL' => 'NL',
            'PT' => 'PT',
        ];

        foreach ($lmChannels as $channelCode => $alias) {
            ChannelPriceMapping::query()->updateOrCreate(
                [
                    'marketplace_id' => $leroymerlin->id,
                    'channel_code' => $channelCode,
                ],
                [
                    'channel_name' => $channelCode,
                    'price_column_id' => $priceColumns[$alias]->id,
                ],
            );
        }

        // Bricodepot shops ES / PT
        MarketplaceShopPrice::query()->updateOrCreate(
            [
                'marketplace_id' => $bricodepot->id,
                'shop_id' => '2102',
            ],
            ['price_column_id' => $priceColumns['ES']->id],
        );

        MarketplaceShopPrice::query()->updateOrCreate(
            [
                'marketplace_id' => $bricodepot->id,
                'shop_id' => '2227',
            ],
            ['price_column_id' => $priceColumns['PT']->id],
        );

        // Default Leclerc → FR price
        MarketplaceShopPrice::query()->updateOrCreate(
            [
                'marketplace_id' => $leclerc->id,
                'shop_id' => '2861',
            ],
            ['price_column_id' => $priceColumns['FR']->id],
        );
    }
}
