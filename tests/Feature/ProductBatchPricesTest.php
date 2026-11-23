<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Models\FeedFile;
use App\Models\PendingPriceChange;
use App\Models\PriceColumn;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductBatchPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_queue_multiple_pending_prices_in_one_request(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = storage_path('framework/testing/batch_prices.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\tPrix_IT\nABC1\tProduit\t10.00\t11.00\n");

        $feed = FeedFile::query()->create([
            'name' => 'Batch feed',
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
            'is_active' => true,
        ]);
        PriceColumn::query()->create([
            'feed_file_id' => $feed->id,
            'name' => 'Prix Italie',
            'csv_column' => 'Prix_IT',
            'is_active' => true,
        ]);
        app(FeedSnapshotService::class)->refresh($feed);

        $this->actingAs($user)
            ->postJson(route('api.products.prices.batch', [$feed, 0]), [
                'prices' => [
                    ['csv_column' => 'Prix_TTC', 'new_price' => 12.5],
                    ['csv_column' => 'Prix_IT', 'new_price' => 13.5],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->assertSame(2, PendingPriceChange::query()->count());
        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'new_price' => '12.50',
        ]);
        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_IT',
            'new_price' => '13.50',
        ]);
    }

    public function test_batch_prices_rejects_unknown_csv_column_with_422(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = storage_path('framework/testing/batch_prices_bad.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t10.00\n");

        $feed = FeedFile::query()->create([
            'name' => 'Batch feed bad',
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
            'is_active' => true,
        ]);
        app(FeedSnapshotService::class)->refresh($feed);

        $this->actingAs($user)
            ->postJson(route('api.products.prices.batch', [$feed, 0]), [
                'prices' => [
                    ['csv_column' => 'Prix_INEXISTANT', 'new_price' => 12.5],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Colonne prix invalide : Prix_INEXISTANT');

        $this->assertSame(0, PendingPriceChange::query()->count());
    }

    public function test_batch_prices_requires_at_least_one_price(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = storage_path('framework/testing/batch_prices_empty.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t10.00\n");

        $feed = FeedFile::query()->create([
            'name' => 'Batch empty',
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
            'is_active' => true,
        ]);
        app(FeedSnapshotService::class)->refresh($feed);

        $this->actingAs($user)
            ->postJson(route('api.products.prices.batch', [$feed, 0]), [
                'prices' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prices']);
    }
}
