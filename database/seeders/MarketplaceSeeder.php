<?php

namespace Database\Seeders;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Models\Currency;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\MarketplacePriceMapping;
use App\Models\PriceColumn;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $feed = FeedFile::query()->updateOrCreate(
            ['name' => 'Flux Lengow Sun Cdiscount'],
            [
                'source_type' => FeedSourceType::Sftp,
                'location' => '/test_clement/Flux_Lengow_Sun_CDISCOUNT.csv',
                'write_source_type' => FeedSourceType::Sftp,
                'write_location' => '/test_clement/Flux_Lengow_Sun_CDISCOUNT.csv',
                'delimiter' => "\t",
                'sku_column' => 'Identifiant_unique',
                'name_column' => 'Titre',
                'is_active' => true,
            ],
        );

        $eurId = Currency::query()->where('code', 'EUR')->value('id');
        $plnId = Currency::query()->where('code', 'PLN')->value('id');

        $columns = [
            ['name' => 'Prix France', 'csv_column' => 'Prix_TTC', 'alias' => 'FR', 'currency_id' => $eurId],
            ['name' => 'Prix Italie', 'csv_column' => 'Prix_TTC_Italie', 'alias' => 'IT', 'currency_id' => $eurId],
            ['name' => 'Prix Espagne', 'csv_column' => 'Prix_TTC_Espagne', 'alias' => 'ES', 'currency_id' => $eurId],
            ['name' => 'Prix Allemagne', 'csv_column' => 'Prix_TTC_Allemand', 'alias' => 'DE', 'currency_id' => $eurId],
            ['name' => 'Prix Pays-Bas', 'csv_column' => 'Prix_TTC_NL', 'alias' => 'NL', 'currency_id' => $eurId],
            ['name' => 'Prix Portugal', 'csv_column' => 'Prix_TTC_Portugal', 'alias' => 'PT', 'currency_id' => $eurId],
            ['name' => 'Prix Pologne', 'csv_column' => 'Prix_TTC_Pologne', 'alias' => 'PL', 'currency_id' => $plnId],
            ['name' => 'Prix BUT', 'csv_column' => 'Prix_BUT', 'alias' => 'BUT', 'currency_id' => $eurId],
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
                    'currency_id' => $column['currency_id'],
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
                'is_active' => true,
            ],
        );

        MarketplacePriceMapping::query()->updateOrCreate(
            [
                'marketplace_id' => $leclerc->id,
                'shop_id' => '2861',
                'channel_code' => '',
            ],
            ['price_column_id' => $priceColumns['FR']->id, 'channel_name' => null],
        );

        foreach (['FR', 'IT', 'ES', 'DE', 'NL', 'PT'] as $channelCode) {
            MarketplacePriceMapping::query()->updateOrCreate(
                [
                    'marketplace_id' => $leroymerlin->id,
                    'shop_id' => '2062',
                    'channel_code' => $channelCode,
                ],
                [
                    'channel_name' => $channelCode,
                    'price_column_id' => $priceColumns[$channelCode]->id,
                ],
            );
        }

        MarketplacePriceMapping::query()->updateOrCreate(
            [
                'marketplace_id' => $bricodepot->id,
                'shop_id' => '2102',
                'channel_code' => '',
            ],
            ['price_column_id' => $priceColumns['ES']->id, 'channel_name' => null],
        );
        MarketplacePriceMapping::query()->updateOrCreate(
            [
                'marketplace_id' => $bricodepot->id,
                'shop_id' => '2227',
                'channel_code' => '',
            ],
            ['price_column_id' => $priceColumns['PT']->id, 'channel_name' => null],
        );
    }
}
