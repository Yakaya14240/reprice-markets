<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Enums\PushBatchStatus;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PriceChangeLog;
use App\Models\PriceColumn;
use App\Models\PricePushBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSuiviFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_suivi_filters_pushes_by_marketplace_and_operator(): void
    {
        $viewer = User::factory()->admin()->create();
        $alice = User::factory()->create(['name' => 'Alice Op']);
        $bob = User::factory()->create(['name' => 'Bob Op']);

        $alpha = Marketplace::query()->create([
            'name' => 'Alpha MKP',
            'slug' => 'alpha-mkp',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://alpha.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);
        $beta = Marketplace::query()->create([
            'name' => 'Beta MKP',
            'slug' => 'beta-mkp',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://beta.test/api',
            'shop_ids' => ['2'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'Catalogue',
            'source_type' => FeedSourceType::Local,
            'location' => 'feeds/catalogue.csv',
            'is_active' => true,
        ]);

        PriceColumn::query()->create([
            'feed_file_id' => $feed->id,
            'name' => 'Prix France',
            'csv_column' => 'price_eur',
            'alias' => 'FR',
            'is_active' => true,
        ]);

        PriceChangeLog::query()->create([
            'user_id' => $alice->id,
            'feed_file_id' => $feed->id,
            'sku' => 'SKU-ALICE',
            'csv_column' => 'price_eur',
            'old_price' => 10,
            'new_price' => 12,
        ]);
        PriceChangeLog::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'SKU-BOB',
            'csv_column' => 'price_eur',
            'old_price' => 8,
            'new_price' => 9,
        ]);

        PricePushBatch::query()->create([
            'user_id' => $alice->id,
            'marketplace_id' => $alpha->id,
            'feed_file_id' => $feed->id,
            'status' => PushBatchStatus::Succeeded,
            'offers_count' => 1,
            'result_message' => 'push alpha alice',
        ]);
        PricePushBatch::query()->create([
            'user_id' => $bob->id,
            'marketplace_id' => $beta->id,
            'feed_file_id' => $feed->id,
            'status' => PushBatchStatus::Failed,
            'offers_count' => 1,
            'result_message' => 'push beta bob',
        ]);

        $this->actingAs($viewer)
            ->get(route('dashboard', [
                'user_id' => $alice->id,
                'marketplace_id' => $alpha->id,
                'status' => PushBatchStatus::Succeeded->value,
            ]))
            ->assertOk()
            ->assertSee('SKU-ALICE', false)
            ->assertDontSee('SKU-BOB', false)
            ->assertSee('France', false)
            ->assertSee('push alpha alice', false)
            ->assertDontSee('push beta bob', false)
            ->assertSee('Modifs aujourd', false)
            ->assertSee('badge-ok', false)
            ->assertSee('Filtrer', false);
    }

    public function test_suivi_rejects_invalid_date_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', [
                'from' => '2026-11-10',
                'to' => '2026-11-01',
            ]))
            ->assertSessionHasErrors(['to']);
    }
}
