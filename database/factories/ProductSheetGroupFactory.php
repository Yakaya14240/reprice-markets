<?php

namespace Database\Factories;

use App\Models\ProductSheetGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSheetGroup>
 */
class ProductSheetGroupFactory extends Factory
{
    protected $model = ProductSheetGroup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}
