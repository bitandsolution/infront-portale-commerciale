<?php

namespace Database\Factories;

use App\Enums\CompetitionType;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious competitions (league, tier 3, no top match KPI). The competitions of the system data
 * (Serie A, B, C groups) come from the seeder.
 *
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 999);

        return [
            'discipline_id' => fn (): int => DisciplineFactory::football()->id,
            'code' => 'LEGA_'.$number,
            'name' => 'Lega di prova '.$number,
            'short_name' => 'Lega '.$number,
            'type' => CompetitionType::League,
            'tier' => 3,
            'has_top_match_kpi' => false,
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}
