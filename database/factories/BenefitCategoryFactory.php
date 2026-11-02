<?php

namespace Database\Factories;

use App\Models\BenefitCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious benefit categories. The 8 categories of the system data come from the seeder.
 *
 * @extends Factory<BenefitCategory>
 */
class BenefitCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CATEGORIA_'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Categoria '.fake()->unique()->word(),
            'sort_order' => 100,
        ];
    }
}
