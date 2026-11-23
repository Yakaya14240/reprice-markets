<?php

namespace Tests\Unit;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Models\Currency;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\MarketplacePriceMapping;
use App\Models\PendingPriceChange;
use App\Models\PriceColumn;
use App\Models\User;
use App\Services\Marketplace\OfferAssembler;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OfferAssemblerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assembles_mono_shop_offers_from_shop_mapping(): void
    {
        [$feed, $headers, $products, $pending] = $this->seedCatalog();

        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['2861'],
            'is_active' => true,
        ]);

        $fr = PriceColumn::query()->where('alias', 'FR')->firstOrFail();
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2861',
            'channel_code' => '',
            'price_column_id' => $fr->id,
        ]);

        $offers = app(OfferAssembler::class)->offersFor(
            $marketplace->fresh(),
            $feed,
            $pending,
            $headers,
            $products,
        );

        $this->assertCount(1, $offers);
        $this->assertSame('ABC1', $offers[0]->sku);
        $this->assertSame(12.5, $offers[0]->price);
        $this->assertSame('2861', $offers[0]->shopId);
        $this->assertSame([], $offers[0]->channels);
        $this->assertTrue($offers[0]->needsMiraklFallback);
    }

    public function test_assembles_discount_when_sale_below_public_price(): void
    {
        [$feed, $headers, $products, $pending] = $this->seedCatalog(withPublicPrice: true);

        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-discount',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['2861'],
            'is_active' => true,
        ]);

        $fr = PriceColumn::query()->where('alias', 'FR')->firstOrFail();
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2861',
            'channel_code' => '',
            'price_column_id' => $fr->id,
        ]);

        $offers = app(OfferAssembler::class)->offersFor(
            $marketplace->fresh(),
            $feed,
            $pending,
            $headers,
            $products,
        );

        $this->assertCount(1, $offers);
        $this->assertSame(20.0, $offers[0]->price);
        $this->assertSame(12.5, $offers[0]->discountPrice);
        $this->assertTrue($offers[0]->needsMiraklDates);
        $this->assertFalse($offers[0]->needsMiraklFallback);
    }

    public function test_assembles_multichannel_offers_from_post_sync_prices(): void
    {
        [$feed, $headers, $products, $pending] = $this->seedCatalog();

        $marketplace = Marketplace::query()->create([
            'name' => 'Leroy Merlin',
            'slug' => 'leroymerlin',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['2062'],
            'is_active' => true,
        ]);

        $fr = PriceColumn::query()->where('alias', 'FR')->firstOrFail();
        $it = PriceColumn::query()->where('alias', 'IT')->firstOrFail();
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2062',
            'channel_code' => 'FR',
            'channel_name' => 'France',
            'price_column_id' => $fr->id,
        ]);
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2062',
            'channel_code' => 'IT',
            'channel_name' => 'Italie',
            'price_column_id' => $it->id,
        ]);

        $offers = app(OfferAssembler::class)->offersFor(
            $marketplace->fresh(),
            $feed,
            $pending,
            $headers,
            $products,
        );

        $this->assertCount(1, $offers);
        $this->assertSame('2062', $offers[0]->shopId);
        $this->assertSame('FR', $offers[0]->channels[0]['id']);
        $this->assertSame(12.5, $offers[0]->channels[0]['price']);
        $this->assertSame('IT', $offers[0]->channels[1]['id']);
        $this->assertSame(14.0, $offers[0]->channels[1]['price']);
        $this->assertTrue($offers[0]->needsMiraklFallback);
    }

    public function test_channel_discount_depends_on_public_price_per_channel(): void
    {
        [$feed, $headers, $products, $pending] = $this->seedCatalog(withPublicPrice: true);

        $marketplace = Marketplace::query()->create([
            'name' => 'Leroy Merlin',
            'slug' => 'leroymerlin-discount',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['2062'],
            'is_active' => true,
        ]);

        $fr = PriceColumn::query()->where('alias', 'FR')->firstOrFail();
        $it = PriceColumn::query()->where('alias', 'IT')->firstOrFail();
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2062',
            'channel_code' => 'FR',
            'channel_name' => 'France',
            'price_column_id' => $fr->id,
        ]);
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2062',
            'channel_code' => 'IT',
            'channel_name' => 'Italie',
            'price_column_id' => $it->id,
        ]);

        // Public = 20; FR sale 12.5 => discount; IT sale 25 => no discount (above public).
        $products[0]['Prix_TTC_Italie'] = '25.00';

        $offers = app(OfferAssembler::class)->offersFor(
            $marketplace->fresh(),
            $feed,
            $pending,
            $headers,
            $products,
        );

        $this->assertCount(1, $offers);
        $this->assertSame(20.0, $offers[0]->channels[0]['price']);
        $this->assertSame(12.5, $offers[0]->channels[0]['discount_price']);
        $this->assertSame(25.0, $offers[0]->channels[1]['price']);
        $this->assertNull($offers[0]->channels[1]['discount_price']);
    }

    public function test_skips_discount_when_public_and_sale_currencies_differ(): void
    {
        $this->seed(CurrencySeeder::class);
        [$feed, $headers, $products, $pending] = $this->seedCatalog(withPublicPrice: true);

        $eurId = Currency::query()->where('code', 'EUR')->value('id');
        $plnId = Currency::query()->where('code', 'PLN')->value('id');

        PriceColumn::query()->where('alias', 'PUBLIC')->update(['currency_id' => $eurId]);
        PriceColumn::query()->where('alias', 'FR')->update(['currency_id' => $plnId]);

        $marketplace = Marketplace::query()->create([
            'name' => 'Leroy Merlin PL',
            'slug' => 'leroymerlin-pl-currency',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['2062'],
            'is_active' => true,
        ]);

        $fr = PriceColumn::query()->where('alias', 'FR')->firstOrFail();
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2062',
            'channel_code' => 'PL',
            'channel_name' => 'Pologne',
            'price_column_id' => $fr->id,
        ]);

        $offers = app(OfferAssembler::class)->offersFor(
            $marketplace->fresh(),
            $feed,
            $pending,
            $headers,
            $products,
        );

        $this->assertCount(1, $offers);
        $this->assertSame(12.5, $offers[0]->channels[0]['price']);
        $this->assertNull($offers[0]->channels[0]['discount_price']);
        $this->assertFalse($offers[0]->needsMiraklDates);
        $this->assertFalse($offers[0]->needsMiraklFallback);
    }

    public function test_assembles_one_offer_per_shop_for_multi_shop(): void
    {
        [$feed, $headers, $products, $pending] = $this->seedCatalog();

        $marketplace = Marketplace::query()->create([
            'name' => 'Bricodepot',
            'slug' => 'bricodepot',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['2102', '2227'],
            'is_active' => true,
        ]);

        $es = PriceColumn::query()->where('alias', 'ES')->firstOrFail();
        $pt = PriceColumn::query()->where('alias', 'PT')->firstOrFail();
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2102',
            'channel_code' => '',
            'price_column_id' => $es->id,
        ]);
        MarketplacePriceMapping::query()->create([
            'marketplace_id' => $marketplace->id,
            'shop_id' => '2227',
            'channel_code' => '',
            'price_column_id' => $pt->id,
        ]);

        $offers = app(OfferAssembler::class)->offersFor(
            $marketplace->fresh(),
            $feed,
            $pending,
            $headers,
            $products,
        );

        $this->assertCount(2, $offers);
        $this->assertSame('2102', $offers[0]->shopId);
        $this->assertSame(11.0, $offers[0]->price);
        $this->assertSame('2227', $offers[1]->shopId);
        $this->assertSame(13.0, $offers[1]->price);
    }

    /**
     * @return array{0: FeedFile, 1: list<string>, 2: list<array<string, string|null>>, 3: Collection<int, PendingPriceChange>}
     */
    private function seedCatalog(bool $withPublicPrice = false): array
    {
        $user = User::factory()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/assembler.csv'),
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        foreach ([
            ['name' => 'FR', 'csv_column' => 'Prix_TTC', 'alias' => 'FR'],
            ['name' => 'IT', 'csv_column' => 'Prix_TTC_Italie', 'alias' => 'IT'],
            ['name' => 'ES', 'csv_column' => 'Prix_TTC_Espagne', 'alias' => 'ES'],
            ['name' => 'PT', 'csv_column' => 'Prix_TTC_Portugal', 'alias' => 'PT'],
        ] as $column) {
            PriceColumn::query()->create([
                'feed_file_id' => $feed->id,
                'name' => $column['name'],
                'csv_column' => $column['csv_column'],
                'alias' => $column['alias'],
                'is_active' => true,
            ]);
        }

        if ($withPublicPrice) {
            PriceColumn::query()->create([
                'feed_file_id' => $feed->id,
                'name' => 'Prix publique',
                'csv_column' => 'Prix_public',
                'alias' => 'PUBLIC',
                'is_active' => true,
                'is_public_price' => true,
            ]);
        }

        $headers = ['Identifiant_unique', 'Titre', 'Prix_TTC', 'Prix_TTC_Italie', 'Prix_TTC_Espagne', 'Prix_TTC_Portugal', 'Prix_public', 'EAN'];
        $products = [[
            'Identifiant_unique' => 'ABC1',
            'Titre' => 'Produit',
            'Prix_TTC' => '12.50',
            'Prix_TTC_Italie' => '14.00',
            'Prix_TTC_Espagne' => '11.00',
            'Prix_TTC_Portugal' => '13.00',
            'Prix_public' => '20.00',
            'EAN' => '1234567890123',
        ]];

        $pending = collect([
            PendingPriceChange::query()->create([
                'user_id' => $user->id,
                'feed_file_id' => $feed->id,
                'sku' => 'ABC1',
                'csv_column' => 'Prix_TTC',
                'old_price' => 10,
                'new_price' => 12.5,
            ]),
        ]);

        return [$feed, $headers, $products, $pending];
    }
}
