<?php

namespace App\Http\Requests\Sports;

use App\Domain\Sports\Data\MatchData;
use App\Enums\MatchdayStage;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Club;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Stadium;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Match typed in the Matches page (M02 F-08, §8): competition of the season, stage, matchday
 * (whole number > 0), date, optional kick-off time, home and away clubs (different), optional
 * stadium. Enrollments, season dates and duplicates are checked by SaveMatch.
 */
class MatchRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $match = $this->route('fixtureMatch');

        return $match instanceof FixtureMatch
            ? $this->user()?->can('update', $match) ?? false
            : $this->user()?->can('create', FixtureMatch::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'competition_season_id' => ['required', 'integer', Rule::exists(CompetitionSeason::class, 'id')],
            'stage' => ['required', 'string', Rule::enum(MatchdayStage::class)],
            'matchday' => ['bail', 'required', 'integer', 'between:1,99'],
            'match_date' => ['required', 'date_format:Y-m-d'],
            'kickoff_time' => ['nullable', 'date_format:H:i'],
            'home_club_id' => ['required', 'integer', Rule::exists(Club::class, 'id')],
            'away_club_id' => ['required', 'integer', 'different:home_club_id', Rule::exists(Club::class, 'id')],
            'stadium_id' => ['nullable', 'integer', Rule::exists(Stadium::class, 'id')],
        ];
    }

    public function matchData(): MatchData
    {
        $time = $this->input('kickoff_time');
        $stadium = $this->input('stadium_id');

        return new MatchData(
            competitionSeasonId: $this->integer('competition_season_id'),
            stage: MatchdayStage::from($this->string('stage')->toString()),
            matchdayNumber: $this->integer('matchday'),
            matchDate: Carbon::parse($this->string('match_date')->toString())->startOfDay(),
            kickoffTime: is_string($time) && $time !== '' ? $time : null,
            homeClubId: $this->integer('home_club_id'),
            awayClubId: $this->integer('away_club_id'),
            stadiumId: $stadium === null || $stadium === '' ? null : $this->integer('stadium_id'),
        );
    }
}
