<?php

namespace Tests\Feature;

use App\Enums\FeedSnapshotStatus;
use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Models\FeedFile;
use App\Models\FeedSnapshot;
use App\Models\Marketplace;
use App\Models\PendingPriceChange;
use App\Models\PriceChangeLog;
use App\Models\PriceColumn;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_sync_pending_prices_to_local_feed(): void
    {
        Storage::fake('local');

        $path = storage_path('framework/testing/sync_feed.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "Identifiant_unique\tTitre\tPrix_TTC\nABC1\tProduit Test\t10.00\n");

        $user = User::factory()->create();
        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-test',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'Local sync feed',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
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

        // Avoid real Mirakl HTTP during sync+publish: sync succeeds first; MKP may fail after.
        $this->actingAs($user)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [$marketplace->id],
            ]);

        $content = file_get_contents($path);
        $this->assertMatchesRegularExpression('/ABC1\t"?Produit Test"?\t12\.50/', $content);

        $this->assertDatabaseHas('price_change_logs', [
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'new_price' => 12.5,
        ]);

        $this->assertSame(0, PendingPriceChange::query()->count());
        $this->assertGreaterThan(0, PriceChangeLog::query()->count());
    }

    public function test_operator_can_sync_csv_without_selecting_marketplaces(): void
    {
        $path = storage_path('framework/testing/sync_feed_only.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "Identifiant_unique\tTitre\tPrix_TTC\nABC1\tProduit Test\t10.00\n");

        $user = User::factory()->create();
        // Needed only to satisfy price_push_batches.marketplace_id FK when logging a sync-only run.
        Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-sync-only',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'Local sync only',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
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
            'new_price' => 15,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
            ])
            ->assertOk();

        $content = file_get_contents($path);
        $this->assertMatchesRegularExpression('/ABC1\t"?Produit Test"?\t15\.00/', $content);
        $this->assertSame(0, PendingPriceChange::query()->count());
    }

    public function test_publish_rejects_url_feed_without_write_location(): void
    {
        $user = User::factory()->create();
        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-url',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'URL feed',
            'source_type' => FeedSourceType::Url,
            'location' => 'https://cdn.example.test/flux.csv',
            'write_source_type' => null,
            'write_location' => null,
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['Identifiant_unique', 'Titre', 'Prix_TTC'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        PendingPriceChange::query()->create([
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 11,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [$marketplace->id],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Destination d’écriture non configurée. Pour un flux URL, renseignez type + chemin d’écriture (local ou SFTP) en admin.']);
    }
}
