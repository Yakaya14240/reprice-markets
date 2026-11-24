<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'FR', 'name' => 'Français', 'sort_order' => 10],
            ['code' => 'DE', 'name' => 'Allemand', 'sort_order' => 20],
            ['code' => 'ES', 'name' => 'Espagnol', 'sort_order' => 30],
            ['code' => 'IT', 'name' => 'Italien', 'sort_order' => 40],
            ['code' => 'PT', 'name' => 'Portugais', 'sort_order' => 50],
            ['code' => 'PL', 'name' => 'Polonais', 'sort_order' => 60],
            ['code' => 'CZ', 'name' => 'Tchèque', 'sort_order' => 70],
        ];

        foreach ($languages as $language) {
            Language::query()->updateOrCreate(
                ['code' => $language['code']],
                [
                    'name' => $language['name'],
                    'is_active' => true,
                    'sort_order' => $language['sort_order'],
                ],
            );
        }
    }
}
