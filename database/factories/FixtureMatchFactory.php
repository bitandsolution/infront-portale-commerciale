<?php

namespace Database\Factories;

use App\Enums\MatchSource;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious scheduled matches. forEdition() puts the match in a matchday of the given edition.
 *
 * @extends Factory<FixtureMatch>
 */
class FixtureMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matchday_id' => fn (): int => Matchday::query()->create([
                'competition_season_id' => CompetitionSeason::factory()->create()->id,
                'number' => 1,
            ])->id,
            'home_club_id' => Club::factory(),
            'away_club_id' => Club::factory(),
            'stadium_id' => null,
            'match_date' => '2051-09-14',
            'kickoff_time' => null,
            'status' => MatchStatus::Scheduled,
            'source' => MatchSource::Manual,
        ];
    }

    public function forEdition(CompetitionSeason $edition, int $matchday = 1): static
    {
        return $this->state(fn (): array => [
            'matchday_id' => Matchday::query()->firstOrCreate([
                'competition_season_id' => $edition->id,
                'stage' => 'regular',
                'number' => $matchday,
            ])->id,
        ]);
    }
}
