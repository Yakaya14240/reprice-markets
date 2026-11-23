<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Models\Currency;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PriceColumn;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCurrencyAndMarketplaceActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_deactivate_marketplace(): void
    {
        $admin = User::factory()->admin()->create();
        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-active',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['2861'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.marketplaces.update', $marketplace), [
                'name' => 'Leclerc',
                'driver' => MarketplaceDriver::Mirakl->value,
                'api_url' => 'https://example.test/api',
                'shop_ids' => '2861',
                // is_active intentionally omitted (unchecked checkbox)
            ])
            ->assertRedirect(route('admin.marketplaces.edit', $marketplace));

        $this->assertFalse($marketplace->fresh()->is_active);
    }

    public function test_admin_can_delete_unused_currency(): void
    {
        $this->seed(CurrencySeeder::class);
        $admin = User::factory()->admin()->create();
        $currency = Currency::query()->create([
            'code' => 'XYZ',
            'name' => 'Test currency',
            'symbol' => '¤',
            'decimals' => 2,
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.currencies.destroy', $currency))
            ->assertRedirect(route('admin.currencies.index'));

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }

    public function test_admin_cannot_delete_currency_used_by_price_column(): void
    {
        $this->seed(CurrencySeeder::class);
        $admin = User::factory()->admin()->create();
        $eur = Currency::query()->where('code', 'EUR')->firstOrFail();

        $feed = FeedFile::query()->create([
            'name' => 'Feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/noop.csv'),
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        PriceColumn::query()->create([
            'feed_file_id' => $feed->id,
            'name' => 'Prix FR',
            'csv_column' => 'Prix_TTC',
            'currency_id' => $eur->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.currencies.destroy', $eur))
            ->assertRedirect(route('admin.currencies.index'));

        $this->assertDatabaseHas('currencies', ['id' => $eur->id]);
    }
}
