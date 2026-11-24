<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Enums\ProductSheetFieldType;
use App\Models\FeedFile;
use App\Models\Language;
use App\Models\Marketplace;
use App\Models\PendingSheetChange;
use App\Models\ProductSheetField;
use App\Models\ProductSheetGroup;
use App\Models\SheetChangeLog;
use App\Models\User;
use App\Services\Feed\FeedSnapshotService;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProductSheetGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSheetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: FeedFile, 3: ProductSheetField, 4: string}
     */
    private function seedSheetFeed(): array
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(ProductSheetGroupSeeder::class);

        $path = storage_path('framework/testing/sheet_workflow.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents(
            $path,
            "SKU\tTitre\tDescription_FR\tLien_FR\nABC1\tProduit A\tAncienne desc\thttps://example.test/a\n"
        );

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-sheet',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'shop_ids' => ['1'],
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'Sheet workflow feed',
            'source_type' => FeedSourceType::Local,
            'location' => $path,
            'write_source_type' => FeedSourceType::Local,
            'write_location' => $path,
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        $language = Language::query()->where('code', 'FR')->firstOrFail();
        $group = ProductSheetGroup::query()->where('name', 'Descriptions')->firstOrFail();
        $field = ProductSheetField::query()->create([
            'feed_file_id' => $feed->id,
            'language_id' => $language->id,
            'product_sheet_group_id' => $group->id,
            'type' => ProductSheetFieldType::TextPlain,
            'label' => 'Description FR',
            'csv_column' => 'Description_FR',
            'is_editable' => true,
            'max_length' => 500,
            'sort_order' => 10,
        ]);

        app(FeedSnapshotService::class)->refresh($feed);

        return [$alice, $bob, $feed, $field, $path];
    }

    public function test_sheet_show_returns_fields_for_language(): void
    {
        [$alice, , $feed, $field] = $this->seedSheetFeed();

        $this->actingAs($alice)
            ->getJson(route('api.sheets.show', [$feed, 0]).'?lang=FR')
            ->assertOk()
            ->assertJsonPath('sku', 'ABC1')
            ->assertJsonPath('fields.0.id', $field->id)
            ->assertJsonPath('fields.0.group_name', 'Descriptions')
            ->assertJsonPath('fields.0.current_value', 'Ancienne desc')
            ->assertJsonPath('fields.0.is_editable', true)
            ->assertJsonPath('groups.0.name', 'Descriptions')
            ->assertJsonPath('groups.0.fields.0.id', $field->id);
    }

    public function test_operator_can_queue_sheet_field_pending_change(): void
    {
        [$alice, , $feed, $field] = $this->seedSheetFeed();

        $this->actingAs($alice)
            ->postJson(route('api.sheets.fields', [$feed, 0]), [
                'fields' => [
                    [
                        'product_sheet_field_id' => $field->id,
                        'new_value' => 'Nouvelle description',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('pending_sheet_changes', [
            'user_id' => $alice->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'product_sheet_field_id' => $field->id,
            'new_value' => 'Nouvelle description',
        ]);
    }

    public function test_sheet_save_returns_conflict_when_colleague_has_pending(): void
    {
        [$alice, $bob, $feed, $field] = $this->seedSheetFeed();

        PendingSheetChange::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'product_sheet_field_id' => $field->id,
            'old_value' => 'Ancienne desc',
            'new_value' => 'Bob desc',
        ]);

        $this->actingAs($alice)
            ->postJson(route('api.sheets.fields', [$feed, 0]), [
                'fields' => [
                    [
                        'product_sheet_field_id' => $field->id,
                        'new_value' => 'Alice desc',
                    ],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'pending_conflicts');

        $this->actingAs($alice)
            ->postJson(route('api.sheets.fields', [$feed, 0]), [
                'fields' => [
                    [
                        'product_sheet_field_id' => $field->id,
                        'new_value' => 'Alice desc',
                    ],
                ],
                'confirm_conflicts' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pending_sheet_changes', [
            'user_id' => $alice->id,
            'new_value' => 'Alice desc',
        ]);
    }

    public function test_publish_writes_sheet_fields_to_csv_and_clears_pending(): void
    {
        [$alice, , $feed, $field, $path] = $this->seedSheetFeed();

        PendingSheetChange::query()->create([
            'user_id' => $alice->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'product_sheet_field_id' => $field->id,
            'old_value' => 'Ancienne desc',
            'new_value' => 'Desc publiée',
        ]);

        $this->actingAs($alice)
            ->postJson(route('api.publish.store', $feed), [
                'marketplace_ids' => [],
            ])
            ->assertOk();

        $content = file_get_contents($path);
        $this->assertStringContainsString('Desc publiée', $content);
        $this->assertSame(0, PendingSheetChange::query()->count());
        $this->assertGreaterThan(0, SheetChangeLog::query()->count());
        $this->assertDatabaseHas('sheet_change_logs', [
            'user_id' => $alice->id,
            'sku' => 'ABC1',
            'csv_column' => 'Description_FR',
            'new_value' => 'Desc publiée',
        ]);
    }

    public function test_pending_endpoint_includes_sheet_queues(): void
    {
        [$alice, $bob, $feed, $field] = $this->seedSheetFeed();

        PendingSheetChange::query()->create([
            'user_id' => $alice->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'product_sheet_field_id' => $field->id,
            'old_value' => 'A',
            'new_value' => 'Alice',
        ]);
        PendingSheetChange::query()->create([
            'user_id' => $bob->id,
            'feed_file_id' => $feed->id,
            'sku' => 'ABC1',
            'product_sheet_field_id' => $field->id,
            'old_value' => 'A',
            'new_value' => 'Bob',
        ]);

        $this->actingAs($alice)
            ->getJson(route('api.pending.index', $feed))
            ->assertOk()
            ->assertJsonPath('pending_sheet.0.sku', 'ABC1')
            ->assertJsonPath('others_sheet.0.user_name', 'Bob');
    }

    public function test_rejects_non_editable_field_updates(): void
    {
        [$alice, , $feed, $field] = $this->seedSheetFeed();
        $field->update(['is_editable' => false]);

        $this->actingAs($alice)
            ->postJson(route('api.sheets.fields', [$feed, 0]), [
                'fields' => [
                    [
                        'product_sheet_field_id' => $field->id,
                        'new_value' => 'Interdit',
                    ],
                ],
            ])
            ->assertStatus(422);
    }
}
