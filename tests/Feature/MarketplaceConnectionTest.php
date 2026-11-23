<?php

namespace Tests\Feature;

use App\Enums\MarketplaceDriver;
use App\Enums\UserRole;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_probe_mirakl_account_endpoint(): void
    {
        Http::fake([
            'https://example.test/api/account*' => Http::response(['shop_name' => 'Demo'], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => 'secret-key',
            'shop_ids' => ['2861'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.marketplaces.test', $marketplace))
            ->assertOk()
            ->assertJsonPath('ok', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/account')
            && str_contains($request->url(), 'shop_id=2861')
            && $request->hasHeader('Authorization', 'secret-key'));
    }

    public function test_mirakl_probe_fails_without_api_key(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketplace = Marketplace::query()->create([
            'name' => 'Leclerc',
            'slug' => 'leclerc-nokey',
            'driver' => MarketplaceDriver::Mirakl,
            'api_url' => 'https://example.test/api',
            'api_key' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.marketplaces.test', $marketplace))
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }
}
