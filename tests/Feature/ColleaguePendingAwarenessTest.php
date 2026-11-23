<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PendingPriceChange;
use App\Models\PriceColumn;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColleaguePendingAwarenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: FeedFile}
     */
    private function seedFeedWithTwoUsers(): array
    {
        $path = storage_path('framework/testing/colleague_pending.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit A\t10.00\nABC2\tProduit B\t20.00\n");

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        Marketplace::query()->create([
            'name' => 'Carrefour FR',
            'slug' => 'carrefour-colleague',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'Colleague feed',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
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

        return [$alice, $bob, $feed];
    }

    public function test_pending_endpoint_includes_other_operators_queue(): void
    {
        [$alice, $bob, $feed] = $this->seedFeedWithTwoUsers();

        PendingPriceChange::query()->create([
            'user_id' => $alice->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 11,
        ]);

        PendingPriceChange::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC2',
            'csv_column' => 'Prix_TTC',
            'old_price' => 20,
            'new_price' => 21.5,
        ]);

        $this->actingAs($alice)
            ->getJson(route('api.pending.index', $feed))
            ->assertOk()
            ->assertJsonPath('pending.0.sku', 'ABC1')
            ->assertJsonPath('others.0.sku', 'ABC2')
            ->assertJsonPath('others.0.user_name', 'Bob')
            ->assertJsonPath('others.0.new_price', '21.50');
    }

    public function test_product_show_includes_colleague_pending_on_price_columns(): void
    {
        [$alice, $bob, $feed] = $this->seedFeedWithTwoUsers();

        PendingPriceChange::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 12.25,
        ]);

        $this->actingAs($alice)
            ->getJson(route('api.products.show', [$feed, 0]))
            ->assertOk()
            ->assertJsonPath('sku', 'ABC1')
            ->assertJsonPath('prices.0.others_pending.0.user_name', 'Bob')
            ->assertJsonPath('prices.0.others_pending.0.new_price', '12.25');
    }

    public function test_saving_prices_warns_when_colleague_has_same_column_pending(): void
    {
        [$alice, $bob, $feed] = $this->seedFeedWithTwoUsers();

        PendingPriceChange::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 12,
        ]);

        $this->actingAs($alice)
            ->postJson(route('api.products.prices.batch', [$feed, 0]), [
                'prices' => [
                    ['csv_column' => 'Prix_TTC', 'new_price' => 13],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'pending_conflicts')
            ->assertJsonPath('conflicts.0.other_user_name', 'Bob');

        $this->assertDatabaseMissing('pending_price_changes', [
            'user_id' => $alice->id,
            'sku' => 'ABC1',
        ]);
    }

    public function test_saving_prices_succeeds_with_confirm_conflicts(): void
    {
        [$alice, $bob, $feed] = $this->seedFeedWithTwoUsers();

        PendingPriceChange::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 12,
        ]);

        $this->actingAs($alice)
            ->postJson(route('api.products.prices.batch', [$feed, 0]), [
                'prices' => [
                    ['csv_column' => 'Prix_TTC', 'new_price' => 13],
                ],
                'confirm_conflicts' => true,
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $alice->id,
            'sku' => 'ABC1',
            'new_price' => '13.00',
        ]);
        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $bob->id,
            'sku' => 'ABC1',
            'new_price' => '12.00',
        ]);
    }
}
