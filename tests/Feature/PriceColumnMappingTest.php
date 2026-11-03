<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\FeedSnapshotStatus;
use App\Enums\UserRole;
use App\Models\FeedFile;
use App\Models\FeedSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceColumnMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_map_price_column_from_discovered_headers(): void
    {
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
            'headers' => ['Identifiant_unique', 'Prix_TTC', 'Titre', 'Prix_TTC_Italie'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.price-columns.store'), [
                'feed_file_id' => $feed->id,
                'name' => 'Prix France',
                'csv_column' => 'Prix_TTC',
                'alias' => 'FR',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('price_columns', [
            'feed_file_id' => $feed->id,
            'csv_column' => 'Prix_TTC',
            'alias' => 'FR',
        ]);
    }

    public function test_admin_cannot_map_unknown_header_when_headers_are_known(): void
    {
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
            ])
            ->assertSessionHasErrors('csv_column');
    }
}
