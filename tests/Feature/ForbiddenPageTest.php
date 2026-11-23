<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForbiddenPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_sees_french_forbidden_page_on_admin(): void
    {
        $operator = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->actingAs($operator)
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Accès refusé', false)
            ->assertSee('Retour aux prix', false);
    }
}
