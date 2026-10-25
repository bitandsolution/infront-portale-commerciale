<?php

namespace Database\Factories;

use App\Models\Stadium;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious stadiums.
 *
 * @extends Factory<Stadium>
 */
class StadiumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Stadio '.fake()->unique()->lastName(),
            'city' => fake()->city(),
            'capacity' => fake()->numberBetween(5000, 70000),
            'is_active' => true,
        ];
    }
}
