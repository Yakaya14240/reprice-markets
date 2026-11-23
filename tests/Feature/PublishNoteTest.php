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
use Tests\TestCase;

class PublishNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_stores_optional_note_on_batch(): void
    {
        $path = storage_path('framework/testing/publish_note.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t10.00\n");

        $user = User::factory()->create();
        $marketplace = Marketplace::query()->create([
            'name' => 'Carrefour FR',
            'slug' => 'carrefour-note',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);
        $feed = FeedFile::query()->create([
            'name' => 'Note feed',
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

        PendingPriceChange::query()->create([
            'user_id' => $user->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'csv_column' => 'Prix_TTC',
            'old_price' => 10,
            'new_price' => 11.5,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
                'note' => '  Alignement promo week-end  ',
            ])
            ->assertOk();

        $batch = PricePushBatch::query()->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame('Alignement promo week-end', $batch->note);
        $this->assertSame($marketplace->id, $batch->marketplace_id);
    }

    public function test_suivi_shows_note_tooltip_when_present(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Admin Note']);
        $marketplace = Marketplace::query()->create([
            'name' => 'Alpha Note',
            'slug' => 'alpha-note',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://alpha-note.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        PricePushBatch::query()->create([
            'user_id' => $user->id,
            'marketplace_id' => $marketplace->id,
            'status' => 'succeeded',
            'offers_count' => 1,
            'result_message' => 'Flux sync OK',
            'note' => 'Correction erreur prix IT',
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alpha Note', false)
            ->assertSee('title="Correction erreur prix IT"', false)
            ->assertSee('Note de publication', false);
    }

    public function test_publish_rejects_note_longer_than_500_chars(): void
    {
        $path = storage_path('framework/testing/publish_note_long.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "SKU\tTitre\tPrix_TTC\nABC1\tProduit\t10.00\n");

        $user = User::factory()->create();
        Marketplace::query()->create([
            'name' => 'Mkp',
            'slug' => 'mkp-note-long',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);
        $feed = FeedFile::query()->create([
            'name' => 'Long note feed',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
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
                'marketplace_ids' => [],
                'note' => str_repeat('a', 501),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }
}
