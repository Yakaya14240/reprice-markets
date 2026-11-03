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

class PricesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_queue_a_pending_price_change(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = storage_path('framework/testing/feed_test.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit Test\t10.00\n");

        $feed = FeedFile::query()->create([
            'name' => 'Local test',
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

        $this->actingAs($user)
            ->postJson(route('api.products.prices', [$feed, 0]), [
                'csv_column' => 'Prix_TTC',
                'new_price' => 12.5,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pending_price_changes', [
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
        ]);

        $this->assertSame(1, PendingPriceChange::query()->count());
    }
}
