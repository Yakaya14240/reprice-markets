<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_is_reachable(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Connexion', false)
            ->assertSee('Agri', false)
            ->assertSee('gator', false)
            ->assertSee('Se connecter', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_admin_dashboard_requires_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_open_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
