<?php

namespace Tests\Feature;

use App\Enums\MarketplaceDriver;
use App\Enums\UserRole;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiraklRemoteChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_mirakl_channels_for_selected_shop(): void
    {
        Http::fake([
            'https://example.test/api/channels*' => Http::response([
                'channels' => [
                    ['code' => 'IT', 'label' => 'Italie', 'description' => 'Italie'],
                    ['code' => 'FR', 'label' => 'France', 'description' => 'France'],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketplace = Marketplace::query()->create([
            'name' => 'Leroy Merlin',
            'slug' => 'leroymerlin',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => ['2062'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.marketplaces.remote-channels', [
                'marketplace' => $marketplace,
                'shop_id' => '2062',
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'channels')
            ->assertJsonPath('channels.0.code', 'FR')
            ->assertJsonPath('channels.0.display', '2062 — France (FR)');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/channels')
            && str_contains($request->url(), 'shop_id=2062'));
    }

    public function test_channels_require_shop_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketplace = Marketplace::query()->create([
            'name' => 'Leroy Merlin',
            'slug' => 'leroymerlin-no-shop',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => ['2062'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.marketplaces.remote-channels', $marketplace))
            ->assertStatus(422);
    }
}
