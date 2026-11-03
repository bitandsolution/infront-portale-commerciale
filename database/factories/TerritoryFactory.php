<?php

namespace Database\Factories;

use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious territories. The territories of the system data (ITALIA, ESTERO) come from the seeder.
 *
 * @extends Factory<Territory>
 */
class TerritoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'TERR_'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Territorio '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
