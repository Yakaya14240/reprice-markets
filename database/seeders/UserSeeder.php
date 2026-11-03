<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@reprice.local'],
            [
                'name' => 'Admin Reprice',
                'password' => Hash::make('ChangeMeNow!'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'operator@reprice.local'],
            [
                'name' => 'Opérateur Reprice',
                'password' => Hash::make('ChangeMeNow!'),
                'role' => UserRole::Operator,
                'is_active' => true,
            ],
        );
    }
}
