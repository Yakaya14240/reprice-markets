<?php

namespace Database\Seeders;

use App\Models\ProductSheetGroup;
use Illuminate\Database\Seeder;

class ProductSheetGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'Titres', 'sort_order' => 10],
            ['name' => 'Stock', 'sort_order' => 20],
            ['name' => 'Descriptions', 'sort_order' => 30],
            ['name' => 'Liens', 'sort_order' => 40],
            ['name' => 'Médias', 'sort_order' => 50],
        ];

        foreach ($groups as $group) {
            ProductSheetGroup::query()->updateOrCreate(
                ['name' => $group['name']],
                [
                    'is_active' => true,
                    'sort_order' => $group['sort_order'],
                ],
            );
        }
    }
}
