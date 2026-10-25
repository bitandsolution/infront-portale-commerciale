<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Editions (competition × season) with 19 standard home matches, as a 20-team league.
 *
 * @extends Factory<CompetitionSeason>
 */
class CompetitionSeasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'season_id' => Season::factory(),
            'standard_home_matches' => 19,
            'calendar_loaded_at' => null,
        ];
    }
}
