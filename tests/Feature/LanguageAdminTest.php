<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\ProductSheetFieldType;
use App\Models\FeedFile;
use App\Models\Language;
use App\Models\ProductSheetField;
use App\Models\User;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_languages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.languages.index'))
            ->assertOk()
            ->assertSee('Langues');

        $this->actingAs($admin)
            ->post(route('admin.languages.store'), [
                'code' => 'nl',
                'name' => 'Néerlandais',
                'sort_order' => 80,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.languages.index'));

        $language = Language::query()->where('code', 'NL')->first();
        $this->assertNotNull($language);
        $this->assertSame('Néerlandais', $language->name);

        $this->actingAs($admin)
            ->put(route('admin.languages.update', $language), [
                'code' => 'NL',
                'name' => 'Néerlandais (BE)',
                'sort_order' => 85,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.languages.index'));

        $this->assertSame('Néerlandais (BE)', $language->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('admin.languages.destroy', $language))
            ->assertRedirect(route('admin.languages.index'));

        $this->assertDatabaseMissing('languages', ['id' => $language->id]);
    }

    public function test_operator_cannot_access_languages_admin(): void
    {
        $operator = User::factory()->create();
        $language = Language::factory()->create(['code' => 'FR', 'name' => 'Français']);

        $this->actingAs($operator)
            ->get(route('admin.languages.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->post(route('admin.languages.store'), [
                'code' => 'DE',
                'name' => 'Allemand',
            ])
            ->assertForbidden();

        $this->actingAs($operator)
            ->delete(route('admin.languages.destroy', $language))
            ->assertForbidden();
    }

    public function test_cannot_delete_language_used_by_sheet_field(): void
    {
        $this->seed(LanguageSeeder::class);
        $admin = User::factory()->admin()->create();
        $language = Language::query()->where('code', 'FR')->firstOrFail();

        $feed = FeedFile::query()->create([
            'name' => 'Feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/lang_feed.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        ProductSheetField::query()->create([
            'feed_file_id' => $feed->id,
            'language_id' => $language->id,
            'type' => ProductSheetFieldType::TextPlain,
            'label' => 'Desc FR',
            'csv_column' => 'Description',
            'is_editable' => false,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.languages.destroy', $language))
            ->assertRedirect(route('admin.languages.index'));

        $this->assertDatabaseHas('languages', ['id' => $language->id]);
    }

    public function test_language_seeder_creates_expected_codes(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->assertSame(
            ['FR', 'DE', 'ES', 'IT', 'PT', 'PL', 'CZ'],
            Language::query()->orderBy('sort_order')->pluck('code')->all()
        );
    }
}
