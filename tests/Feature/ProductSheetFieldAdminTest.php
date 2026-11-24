<?php

namespace Tests\Feature;

use App\Enums\FeedSnapshotStatus;
use App\Enums\FeedSourceType;
use App\Enums\ProductSheetFieldType;
use App\Models\FeedFile;
use App\Models\FeedSnapshot;
use App\Models\Language;
use App\Models\ProductSheetField;
use App\Models\ProductSheetGroup;
use App\Models\User;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProductSheetGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSheetFieldAdminTest extends TestCase
{
    use RefreshDatabase;

    private function seedFeed(): array
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(ProductSheetGroupSeeder::class);
        $admin = User::factory()->admin()->create();
        $feed = FeedFile::query()->create([
            'name' => 'Sheet fields feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/sheet_fields.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);
        FeedSnapshot::query()->create([
            'feed_file_id' => $feed->id,
            'status' => FeedSnapshotStatus::Ready,
            'products_count' => 1,
            'headers' => ['SKU', 'Titre', 'Description_FR', 'Lien_FR', 'Images_FR'],
            'progress' => 100,
            'loaded_at' => now(),
        ]);

        return [$admin, $feed, Language::query()->where('code', 'FR')->firstOrFail()];
    }

    public function test_admin_product_sheet_page_lists_field_configurator(): void
    {
        [$admin, $feed] = $this->seedFeed();

        $this->actingAs($admin)
            ->get(route('admin.product-sheet.index', ['feed_file_id' => $feed->id]))
            ->assertOk()
            ->assertSee('Champs fiche (multi-langues)', false)
            ->assertSee('Zones fiche', false)
            ->assertSee('name="product_sheet_group_id"', false)
            ->assertSee('name="image_columns[]"', false);
    }

    public function test_admin_can_crud_product_sheet_fields(): void
    {
        [$admin, $feed, $language] = $this->seedFeed();
        $descriptions = ProductSheetGroup::query()->where('name', 'Descriptions')->firstOrFail();
        $titres = ProductSheetGroup::query()->where('name', 'Titres')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.product-sheet-fields.store'), [
                'feed_file_id' => $feed->id,
                'language_id' => $language->id,
                'type' => ProductSheetFieldType::TextHtml->value,
                'label' => 'Description FR',
                'product_sheet_group_id' => $descriptions->id,
                'csv_column' => 'Description_FR',
                'is_editable' => '1',
                'max_length' => 2000,
                'help_text' => 'HTML autorisé',
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin.product-sheet.index', ['feed_file_id' => $feed->id]));

        $field = ProductSheetField::query()->first();
        $this->assertNotNull($field);
        $this->assertTrue($field->is_editable);
        $this->assertSame(ProductSheetFieldType::TextHtml, $field->type);
        $this->assertSame($descriptions->id, $field->product_sheet_group_id);

        $this->actingAs($admin)
            ->put(route('admin.product-sheet-fields.update', $field), [
                'language_id' => $language->id,
                'type' => ProductSheetFieldType::TextPlain->value,
                'label' => 'Description plain',
                'product_sheet_group_id' => $titres->id,
                'csv_column' => 'Description_FR',
                'max_length' => 500,
                'help_text' => 'Texte libre',
                'sort_order' => 20,
            ])
            ->assertRedirect(route('admin.product-sheet.index', ['feed_file_id' => $feed->id]));

        $field->refresh();
        $this->assertSame(ProductSheetFieldType::TextPlain, $field->type);
        $this->assertFalse($field->is_editable);
        $this->assertSame(500, $field->max_length);
        $this->assertSame($titres->id, $field->product_sheet_group_id);

        $this->actingAs($admin)
            ->delete(route('admin.product-sheet-fields.destroy', $field))
            ->assertRedirect(route('admin.product-sheet.index', ['feed_file_id' => $feed->id]));

        $this->assertDatabaseMissing('product_sheet_fields', ['id' => $field->id]);
    }

    public function test_operator_cannot_manage_sheet_fields(): void
    {
        [, $feed, $language] = $this->seedFeed();
        $operator = User::factory()->create();

        $this->actingAs($operator)
            ->post(route('admin.product-sheet-fields.store'), [
                'feed_file_id' => $feed->id,
                'language_id' => $language->id,
                'type' => ProductSheetFieldType::HttpLink->value,
                'label' => 'Lien',
                'csv_column' => 'Lien_FR',
            ])
            ->assertForbidden();
    }
}
