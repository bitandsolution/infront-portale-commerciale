<?php

namespace Database\Factories;

use App\Models\LedFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious LED formats of the classic visibility, without pools: a test attaches the pools it needs
 * ($format->pools()->attach(...)). The formats of the system data come from the seeder.
 *
 * @extends Factory<LedFormat>
 */
class LedFormatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'FORMAT_'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Format '.fake()->unique()->word(),
            'description' => null,
            'visibility_type_id' => fn (): int => VisibilityTypeFactory::classic()->id,
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}
