<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\MarketplaceDriver;
use App\Models\Currency;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBoPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_are_paginated_and_nav_links_to_suivi(): void
    {
        $admin = User::factory()->admin()->create();

        for ($i = 1; $i <= 21; $i++) {
            $label = sprintf('%02d', $i);

            Marketplace::query()->create([
                'name' => "Marketplace {$label}",
                'slug' => "marketplace-{$label}",
                'driver' => MarketplaceDriver::Mirakl,
                'api_url' => "https://mkp-{$label}.test/api",
                'shop_ids' => [(string) $i],
                'is_active' => true,
            ]);

            FeedFile::query()->create([
                'name' => "Feed {$label}",
                'source_type' => FeedSourceType::Local,
                'location' => "feeds/feed-{$label}.csv",
                'is_active' => true,
            ]);

            Currency::query()->create([
                'code' => sprintf('C%02d', $i),
                'name' => "Currency {$label}",
                'symbol' => '¤',
                'decimals' => 2,
                'sort_order' => $i,
                'is_active' => true,
            ]);

            if ($i > 1) {
                User::factory()->create([
                    'name' => "User {$label}",
                    'email' => "user{$label}@example.test",
                ]);
            }
        }

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.index'))
            ->assertOk()
            ->assertSee('Marketplace 01', false)
            ->assertDontSee('Marketplace 21', false)
            ->assertSee('?page=2', false)
            ->assertSee('Affichage de', false)
            ->assertSee(route('dashboard'), false)
            ->assertSee('Suivi', false);

        $this->actingAs($admin)
            ->get(route('admin.feeds.index'))
            ->assertOk()
            ->assertSee('Feed 01', false)
            ->assertDontSee('Feed 21', false);

        $this->actingAs($admin)
            ->get(route('admin.currencies.index'))
            ->assertOk()
            ->assertSee('C01', false)
            ->assertDontSee('C21', false);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User 02', false)
            ->assertDontSee('User 21', false);
    }

    public function test_marketplace_edit_shows_secret_keep_on_blank_hint(): void
    {
        $admin = User::factory()->admin()->create();
        $marketplace = Marketplace::query()->create([
            'name' => 'Secret Shop',
            'slug' => 'secret-shop',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://secret.test/api',
            'api_key' => 'existing-key',
            'shop_ids' => ['9'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.edit', $marketplace))
            ->assertOk()
            ->assertSee('laisser vide pour conserver', false)
            ->assertSee('inchangé si vide', false);
    }
}
