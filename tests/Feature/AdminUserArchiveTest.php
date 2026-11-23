<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_deactivate_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => UserRole::Operator->value,
                // is_active intentionally omitted (unchecked checkbox)
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_can_archive_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'email' => 'operator@example.test',
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'email' => 'operator@example.test',
            'deleted_at' => null,
        ]);
        $this->assertFalse(User::query()->whereKey($user->id)->exists());
    }

    public function test_admin_cannot_archive_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users.edit', $admin));

        $this->assertNotSoftDeleted($admin);
        $this->assertTrue($admin->fresh()->is_active);
    }
}
