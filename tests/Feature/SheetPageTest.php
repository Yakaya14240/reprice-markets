<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Models\FeedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SheetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_open_fiches_page(): void
    {
        $operator = User::factory()->create();
        FeedFile::query()->create([
            'name' => 'Active feed',
            'source_type' => FeedSourceType::Local,
            'location' => storage_path('framework/testing/fiches_page.csv'),
            'delimiter' => "\t",
            'sku_column' => 'SKU',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        $this->actingAs($operator)
            ->get(route('sheets.index'))
            ->assertOk()
            ->assertSee('Fiches')
            ->assertSee('Recherche SKU / nom');
    }

    public function test_guest_is_redirected_from_fiches_page(): void
    {
        $this->get(route('sheets.index'))
            ->assertRedirect(route('login'));
    }
}
