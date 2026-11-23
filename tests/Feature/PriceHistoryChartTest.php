<?php

namespace Tests\Feature;

use App\Enums\FeedSnapshotStatus;
use App\Enums\FeedSourceType;
use App\Models\Currency;
use App\Models\FeedFile;
use App\Models\FeedSnapshot;
use App\Models\PriceChangeLog;
use App\Models\PriceColumn;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PriceHistoryChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_unique_chart_default_column_per_feed(): void
    {
        $this->seed(CurrencySeeder::class);
        $admin = User::factory()->admin()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Chart feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/chart.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);
        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['SKU', 'Titre', 'Prix_TTC', 'Prix_IT'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);
        $eurId = Currency::query()->where('code', 'EUR')->value('id');

        $this->actingAs($admin)
            ->post(route('admin.price-columns.store'), [
                'feed_file_id' => $feed->id,
                'name' => 'Prix France',
                'csv_column' => 'Prix_TTC',
                'currency_id' => $eurId,
                'is_chart_default' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.price-columns.store'), [
                'feed_file_id' => $feed->id,
                'name' => 'Prix Italie',
                'csv_column' => 'Prix_IT',
                'currency_id' => $eurId,
                'is_chart_default' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(1, PriceColumn::query()
            ->where('feed_file_id', $feed->id)
            ->where('is_chart_default', true)
            ->count());
        $this->assertDatabaseHas('price_columns', [
            'feed_file_id' => $feed->id,
            'csv_column' => 'Prix_IT',
            'is_chart_default' => true,
        ]);
        $this->assertDatabaseHas('price_columns', [
            'feed_file_id' => $feed->id,
            'csv_column' => 'Prix_TTC',
            'is_chart_default' => false,
        ]);
    }

    public function test_product_payload_exposes_chart_default_and_history(): void
    {
        Storage::fake('local');
        $this->seed(CurrencySeeder::class);

        $user = User::factory()->create();
        $path = storage_path('framework/testing/chart_product.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t10.00\n");

        $feed = FeedFile::query()->create([
            'name' => 'Local chart',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);
        $eurId = Currency::query()->where('code', 'EUR')->value('id');
        PriceColumn::query()->create([
            'feed_file_id' => $feed->id,
            'name' => 'Prix France',
            'csv_column' => 'Prix_TTC',
            'currency_id' => $eurId,
            'alias' => 'FR',
            'is_active' => true,
            'is_chart_default' => true,
        ]);
        app(FeedSnapshotService::class)->refresh($feed);

        PriceChangeLog::query()->create([
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 12.5,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.products.show', [$feed, 0]))
            ->assertOk()
            ->assertJsonPath('chart_default_csv_column', 'Prix_TTC')
            ->assertJsonPath('chart_default_name', 'Prix France')
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.csv_column', 'Prix_TTC')
            ->assertJsonPath('history.0.new_price', '12.50');
    }
}
