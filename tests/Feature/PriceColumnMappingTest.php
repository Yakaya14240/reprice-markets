<?php

namespace Tests\Feature;

use App\Enums\FeedSnapshotStatus;
use App\Enums\FeedSourceType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\FeedFile;
use App\Models\FeedSnapshot;
use App\Models\PriceColumn;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceColumnMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_map_price_column_with_currency(): void
    {
        $this->seed(CurrencySeeder::class);
        $admin = User::factory()->admin()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Test feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/noop.csv'),
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['Identifiant_unique', 'Prix_TTC', 'Titre', 'Prix_TTC_Pologne'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        $plnId = Currency::query()->where('code', 'PLN')->value('id');

        $this->actingAs($admin)
            ->post(route('admin.price-columns.store'), [
                'feed_file_id' => $feed->id,
                'name' => 'Prix Pologne',
                'csv_column' => 'Prix_TTC_Pologne',
                'alias' => 'PL',
                'currency_id' => $plnId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('price_columns', [
            'feed_file_id' => $feed->id,
            'csv_column' => 'Prix_TTC_Pologne',
            'alias' => 'PL',
            'currency_id' => $plnId,
        ]);
    }

    public function test_only_one_public_price_column_per_feed(): void
    {
        $this->seed(CurrencySeeder::class);
        $admin = User::factory()->admin()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Test feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/noop.csv'),
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['Identifiant_unique', 'Prix_TTC', 'Prix_public', 'Titre'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        $eurId = Currency::query()->where('code', 'EUR')->value('id');

        $this->actingAs($admin)
            ->post(route('admin.price-columns.store'), [
                'feed_file_id' => $feed->id,
                'name' => 'Prix publique A',
                'csv_column' => 'Prix_public',
                'currency_id' => $eurId,
                'is_public_price' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.price-columns.store'), [
                'feed_file_id' => $feed->id,
                'name' => 'Prix publique B',
                'csv_column' => 'Prix_TTC',
                'currency_id' => $eurId,
                'is_public_price' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(1, PriceColumn::query()
            ->where('feed_file_id', $feed->id)
            ->where('is_public_price', true)
            ->count());
        $this->assertDatabaseHas('price_columns', [
            'feed_file_id' => $feed->id,
            'csv_column' => 'Prix_TTC',
            'is_public_price' => true,
        ]);
        $this->assertDatabaseHas('price_columns', [
            'feed_file_id' => $feed->id,
            'csv_column' => 'Prix_public',
            'is_public_price' => false,
        ]);
    }

    public function test_admin_cannot_map_unknown_header_when_headers_are_known(): void
    {
        $this->seed(CurrencySeeder::class);
        $admin = User::factory()->admin()->create(['role' => UserRole::Admin]);
        $feed = FeedFile::query()->create([
            'name' => 'Test feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/noop.csv'),
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['Identifiant_unique', 'Prix_TTC', 'Titre'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.price-columns.index', ['feed_file_id' => $feed->id]))
            ->post(route('admin.price-columns.store'), [
                'feed_file_id' => $feed->id,
                'name' => 'Inconnu',
                'csv_column' => 'Colonne_Inventee',
                'currency_id' => Currency::query()->where('code', 'EUR')->value('id'),
            ])
            ->assertSessionHasErrors('csv_column');
    }

    public function test_price_columns_index_paginates_and_renders_eur_option(): void
    {
        $this->seed(CurrencySeeder::class);
        $admin = User::factory()->admin()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Paginated feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/paginate.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);
        $eurId = Currency::query()->where('code', 'EUR')->value('id');

        for ($i = 1; $i <= 21; $i++) {
            PriceColumn::query()->create([
                'feed_file_id' => $feed->id,
                'name' => sprintf('Colonne %02d', $i),
                'csv_column' => sprintf('Prix_%02d', $i),
                'currency_id' => $eurId,
                'alias' => sprintf('C%02d', $i),
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.price-columns.index', ['feed_file_id' => $feed->id]))
            ->assertOk()
            ->assertSee('Colonne 01', false)
            ->assertDontSee('Colonne 21', false)
            ->assertSee('page=2', false)
            ->assertDontSee("EUR')\"", false)
            ->assertSee('Affichage de', false);
    }
}
