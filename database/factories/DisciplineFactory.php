<?php

namespace Database\Factories;

use App\Models\Discipline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Disciplines for the tests. football() returns the discipline of the system data (CALCIO),
 * creating it when the seeder has not run.
 *
 * @extends Factory<Discipline>
 */
class DisciplineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SPORT_'.fake()->unique()->numberBetween(100, 999),
            'name' => fake()->word(),
            'is_active' => true,
            'sort_order' => 100,
        ];
    }

    public static function football(): Discipline
    {
        return Discipline::query()->firstOrCreate(
            ['code' => Discipline::FOOTBALL],
            ['name' => 'Calcio', 'is_active' => true, 'sort_order' => 10],
        );
    }
}
