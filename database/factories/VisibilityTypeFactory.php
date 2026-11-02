<?php

namespace Database\Factories;

use App\Models\VisibilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious visibility types. classic() returns the type of the system data (CLASSICA), creating
 * it when the seeder has not run.
 *
 * @extends Factory<VisibilityType>
 */
class VisibilityTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'VIS_'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Visibilità '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }

    public static function classic(): VisibilityType
    {
        return VisibilityType::query()->firstOrCreate(
            ['code' => 'CLASSICA'],
            ['name' => 'Classica', 'is_active' => true],
        );
    }
}
