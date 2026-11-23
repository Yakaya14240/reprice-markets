<?php

namespace Tests\Feature;

use App\Enums\MarketplaceDriver;
use App\Enums\UserRole;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiraklRemoteShopsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_mirakl_shops_without_persisting(): void
    {
        Http::fake([
            'https://example.test/api/shops*' => Http::response([
                'shops' => [
                    ['shop_id' => 2227, 'shop_name' => 'Portugal', 'shop_state' => 'OPEN'],
                    ['shop_id' => 2102, 'shop_name' => 'Espagne', 'shop_state' => 'OPEN'],
                ],
                'total_count' => 2,
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketplace = Marketplace::query()->create([
            'name' => 'Bricodepot',
            'slug' => 'bricodepot',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.marketplaces.remote-shops', $marketplace))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'shops');

        $marketplace->refresh();
        $this->assertSame([], $marketplace->shop_ids);
    }

    public function test_shops_fallback_to_account_when_list_forbidden(): void
    {
        Http::fake([
            'https://example.test/api/shops*' => Http::response(['message' => 'Forbidden', 'status' => 403], 403),
            'https://example.test/api/account*' => Http::response([
                'shop_id' => 2062,
                'shop_name' => 'Leroy Merlin',
                'shop_state' => 'OPEN',
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketplace = Marketplace::query()->create([
            'name' => 'Leroy Merlin',
            'slug' => 'leroymerlin-fallback',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.marketplaces.remote-shops', $marketplace))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('source', 'account')
            ->assertJsonPath('shops.0.id', '2062');

        $marketplace->refresh();
        $this->assertSame([], $marketplace->shop_ids);
    }
}
