<?php

namespace Tests\Feature;

use App\Enums\FeedSnapshotStatus;
use App\Enums\FeedSourceType;
use App\Models\FeedFile;
use App\Models\FeedSnapshot;
use App\Models\PriceColumn;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSheetMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_sheet_page_renders_image_zone_instead_of_checkbox_grid(): void
    {
        $admin = User::factory()->admin()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Sheet feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/sheet.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'image_columns' => ['Image1'],
            'is_active' => true,
        ]);
        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['SKU', 'Titre', 'Description', 'Image1', 'Image2'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.product-sheet.index', ['feed_file_id' => $feed->id]))
            ->assertOk()
            ->assertSee('Zone images', false)
            ->assertSee('Ajouter une image', false)
            ->assertSee('name="image_columns[]"', false)
            ->assertDontSee('name="image_columns[]" type="checkbox"', false);
    }

    public function test_admin_can_save_product_sheet_mapping(): void
    {
        $admin = User::factory()->admin()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Sheet feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/sheet.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);
        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['SKU', 'Titre', 'Description', 'Image1', 'Image2'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.product-sheet.update', $feed), [
                'description_column' => 'Description',
                'image_columns' => ['Image1', 'Image2'],
            ])
            ->assertRedirect(route('admin.product-sheet.index', ['feed_file_id' => $feed->id]));

        $feed->refresh();
        $this->assertSame('Description', $feed->description_column);
        $this->assertSame(['Image1', 'Image2'], $feed->image_columns);
    }

    public function test_operator_cannot_access_product_sheet_mapping(): void
    {
        $operator = User::factory()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Sheet feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/sheet.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        $this->actingAs($operator)
            ->get(route('admin.product-sheet.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->put(route('admin.product-sheet.update', $feed), [
                'description_column' => 'Description',
            ])
            ->assertForbidden();
    }

    public function test_product_show_exposes_description_and_images_from_sheet_mapping(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = storage_path('framework/testing/sheet_product.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents(
            $path,
            "SKU\tTitre\tDescription\tImage1\tPrix_TTC\n".
            "ABC1\tProduit\t<strong>Pour bénéficier de la Garantie<br/>Pièces 2 ans</strong>\thttps://cdn.example.com/a.jpg|https://cdn.example.com/b.jpg\t10.00\n"
        );

        $feed = FeedFile::query()->create([
            'name' => 'Local sheet',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'description_column' => 'Description',
            'image_columns' => ['Image1'],
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
            ->getJson(route('api.products.show', [$feed, 0]))
            ->assertOk()
            ->assertJsonPath('sku', 'ABC1')
            ->assertJsonPath('name', 'Produit')
            ->assertJsonPath('description', 'Pour bénéficier de la Garantie Pièces 2 ans')
            ->assertJsonPath('images.0', 'https://cdn.example.com/a.jpg')
            ->assertJsonPath('images.1', 'https://cdn.example.com/b.jpg');
    }
}
