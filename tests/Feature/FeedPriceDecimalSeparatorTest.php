<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Enums\PriceDecimalSeparator;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PendingPriceChange;
use App\Models\PriceColumn;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use App\Support\PriceDecimalFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedPriceDecimalSeparatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_price_decimal_separator_on_feed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.feeds.store'), [
                'name' => 'Flux virgule',
                'source_type' => FeedSourceType::Local->value,
                'location' => storage_path('framework/testing/decimal_feed.csv'),
                'delimiter' => '\t',
                'price_decimal_separator' => PriceDecimalSeparator::Comma->value,
                'sku_column' => 'SKU',
                'name_column' => 'Titre',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $feed = FeedFile::query()->where('name', 'Flux virgule')->first();
        $this->assertNotNull($feed);
        $this->assertSame(PriceDecimalSeparator::Comma, $feed->price_decimal_separator);

        $this->actingAs($admin)
            ->put(route('admin.feeds.update', $feed), [
                'name' => 'Flux virgule',
                'source_type' => FeedSourceType::Local->value,
                'location' => $feed->location,
                'delimiter' => '\t',
                'price_decimal_separator' => PriceDecimalSeparator::Dot->value,
                'sku_column' => 'SKU',
                'name_column' => 'Titre',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(PriceDecimalSeparator::Dot, $feed->fresh()->price_decimal_separator);
    }

    public function test_publish_writes_prices_with_comma_decimal_separator(): void
    {
        $path = storage_path('framework/testing/comma_price_feed.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t10,00\n");

        $user = User::factory()->create();
        Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-comma-price',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'Comma feed',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'price_decimal_separator' => PriceDecimalSeparator::Comma,
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        PriceColumn::query()->create([
            'feed_file_id' => $feed->id,
            'name' => 'Prix France',
            'csv_column' => 'Prix_TTC',
            'alias' => 'FR',
            'is_active' => true,
        ]);

        app(FeedSnapshotService::class)->refresh($feed);

        PendingPriceChange::query()->create([
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 12.5,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
            ])
            ->assertOk();

        $content = file_get_contents($path);
        $this->assertMatchesRegularExpression('/ABC1\t"?Produit"?\t12,50/', $content);
        $this->assertDoesNotMatchRegularExpression('/12\.50/', $content);
    }

    public function test_product_show_normalizes_comma_prices_for_inputs(): void
    {
        $path = storage_path('framework/testing/comma_show_feed.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t19,99\n");

        $user = User::factory()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Comma show feed',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'price_decimal_separator' => PriceDecimalSeparator::Comma,
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        PriceColumn::query()->create([
            'feed_file_id' => $feed->id,
            'name' => 'Prix France',
            'csv_column' => 'Prix_TTC',
            'alias' => 'FR',
            'is_active' => true,
        ]);

        app(FeedSnapshotService::class)->refresh($feed);

        $this->actingAs($user)
            ->getJson(route('api.products.show', [$feed, 0]))
            ->assertOk()
            ->assertJsonPath('prices.0.current_price', '19.99')
            ->assertJsonPath('prices.0.current_price_raw', '19,99');
    }

    public function test_pending_price_parses_comma_old_value_from_feed(): void
    {
        $path = storage_path('framework/testing/comma_pending_feed.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t8,40\n");

        $user = User::factory()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Comma pending feed',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'price_decimal_separator' => PriceDecimalSeparator::Comma,
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        PriceColumn::query()->create([
            'feed_file_id' => $feed->id,
            'name' => 'Prix France',
            'csv_column' => 'Prix_TTC',
            'alias' => 'FR',
            'is_active' => true,
        ]);

        app(FeedSnapshotService::class)->refresh($feed);

        $this->actingAs($user)
            ->postJson(route('api.products.prices.batch', [$feed, 0]), [
                'prices' => [
                    ['csv_column' => 'Prix_TTC', 'new_price' => 9.1],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $user->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 8.4,
            'new_price' => 9.1,
        ]);
    }

    public function test_formatter_handles_common_price_shapes(): void
    {
        $formatter = app(PriceDecimalFormatter::class);

        $this->assertSame(12.5, $formatter->parse('12,50'));
        $this->assertSame(12.5, $formatter->parse('12.50'));
        $this->assertSame(1234.56, $formatter->parse('1.234,56'));
        $this->assertSame('12,50', $formatter->format(12.5, PriceDecimalSeparator::Comma));
        $this->assertSame('12.50', $formatter->format(12.5, PriceDecimalSeparator::Dot));
    }
}
