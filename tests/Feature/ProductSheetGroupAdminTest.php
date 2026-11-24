<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\ProductSheetFieldType;
use App\Models\FeedFile;
use App\Models\Language;
use App\Models\ProductSheetField;
use App\Models\ProductSheetGroup;
use App\Models\User;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProductSheetGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSheetGroupAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_sheet_groups(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.product-sheet-groups.index'))
            ->assertOk()
            ->assertSee('Zones fiche');

        $this->actingAs($admin)
            ->post(route('admin.product-sheet-groups.store'), [
                'name' => 'Promo',
                'sort_order' => 15,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.product-sheet-groups.index'));

        $group = ProductSheetGroup::query()->where('name', 'Promo')->first();
        $this->assertNotNull($group);

        $this->actingAs($admin)
            ->put(route('admin.product-sheet-groups.update', $group), [
                'name' => 'Promotions',
                'sort_order' => 16,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.product-sheet-groups.index'));

        $this->assertSame('Promotions', $group->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('admin.product-sheet-groups.destroy', $group))
            ->assertRedirect(route('admin.product-sheet-groups.index'));

        $this->assertDatabaseMissing('product_sheet_groups', ['id' => $group->id]);
    }

    public function test_cannot_delete_group_used_by_field(): void
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(ProductSheetGroupSeeder::class);

        $admin = User::factory()->admin()->create();
        $group = ProductSheetGroup::query()->where('name', 'Titres')->firstOrFail();
        $language = Language::query()->where('code', 'FR')->firstOrFail();
        $feed = FeedFile::query()->create([
            'name' => 'Feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/group_feed.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        ProductSheetField::query()->create([
            'feed_file_id' => $feed->id,
            'language_id' => $language->id,
            'product_sheet_group_id' => $group->id,
            'type' => ProductSheetFieldType::TextLine,
            'label' => 'Titre FR',
            'csv_column' => 'Titre',
            'is_editable' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.product-sheet-groups.destroy', $group))
            ->assertRedirect(route('admin.product-sheet-groups.index'));

        $this->assertDatabaseHas('product_sheet_groups', ['id' => $group->id]);
    }

    public function test_operator_cannot_access_sheet_groups_admin(): void
    {
        $operator = User::factory()->create();

        $this->actingAs($operator)
            ->get(route('admin.product-sheet-groups.index'))
            ->assertForbidden();
    }

    public function test_seeder_creates_default_groups(): void
    {
        $this->seed(ProductSheetGroupSeeder::class);

        $this->assertSame(
            ['Titres', 'Stock', 'Descriptions', 'Liens', 'Médias'],
            ProductSheetGroup::query()->orderBy('sort_order')->pluck('name')->all()
        );
    }
}
