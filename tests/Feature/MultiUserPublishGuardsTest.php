<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PendingPriceChange;
use App\Models\PriceColumn;
use App\Models\PricePushBatch;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MultiUserPublishGuardsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: FeedFile}
     */
    private function seedFeedWithTwoUsers(): array
    {
        $path = storage_path('framework/testing/multi_user_publish.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit A\t10.00\nABC2\tProduit B\t20.00\n");

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        Marketplace::query()->create([
            'name' => 'Carrefour FR',
            'slug' => 'carrefour-multi-user',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'Multi-user feed',
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

    public function test_publish_returns_conflicts_when_other_user_overlaps_same_sku(): void
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
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 12,
        ]);

        $this->actingAs($alice)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'pending_conflicts')
            ->assertJsonPath('conflicts.0.sku', 'ABC1')
            ->assertJsonPath('conflicts.0.other_user_name', 'Bob');

        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $alice->id,
            'sku' => 'ABC1',
        ]);
        $this->assertSame(0, PricePushBatch::query()->count());
    }

    public function test_publish_succeeds_with_confirm_conflicts_when_overlap_exists(): void
    {
        [$alice, $bob, $feed] = $this->seedFeedWithTwoUsers();

        PendingPriceChange::query()->create([
            'user_id' => $alice->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 11.5,
        ]);

        PendingPriceChange::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 12,
        ]);

        $this->actingAs($alice)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
                'confirm_conflicts' => true,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('pending_price_changes', [
            'user_id' => $alice->id,
            'sku' => 'ABC1',
        ]);
        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $bob->id,
            'sku' => 'ABC1',
            'new_price' => '12.00',
        ]);
        $this->assertSame(1, PricePushBatch::query()->count());
    }

    public function test_publish_without_confirm_succeeds_when_skus_are_disjoint(): void
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
            'new_price' => 21,
        ]);

        $this->actingAs($alice)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('pending_price_changes', [
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $bob->id,
            'sku' => 'ABC2',
        ]);
    }

    public function test_publish_returns_409_when_feed_lock_is_held(): void
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

        $lock = Cache::lock("publish_lock_feed_{$feed->id}", 60);
        $this->assertTrue($lock->get());
        Cache::put("publish_feed_holder_{$feed->id}", [
            'user_id' => $bob->id,
            'name' => 'Bob',
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        try {
            $this->actingAs($alice)
                ->postJson(route('api.publish.store', $feed), [
                    'marketplace_ids' => [],
                ])
                ->assertStatus(409)
                ->assertJsonPath('code', 'feed_publish_lock')
                ->assertJsonPath('holder.name', 'Bob');
        } finally {
            Cache::forget("publish_feed_holder_{$feed->id}");
            $lock->release();
        }

        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $alice->id,
            'sku' => 'ABC1',
        ]);
    }

    public function test_publish_succeeds_after_feed_lock_is_released(): void
    {
        [$alice, , $feed] = $this->seedFeedWithTwoUsers();

        PendingPriceChange::query()->create([
            'user_id' => $alice->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 11,
        ]);

        $lock = Cache::lock("publish_lock_feed_{$feed->id}", 60);
        $this->assertTrue($lock->get());
        $lock->release();

        $this->actingAs($alice)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('pending_price_changes', [
            'user_id' => $alice->id,
        ]);
    }
}
