<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\MatchData;
use App\Domain\Sports\Data\SavedMatch;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\MatchSource;
use App\Models\Club;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * New match or change of a match from the Matches page (M02 F-08, FL-03): admin and manager (D-137).
 *
 * Rules (M02 §8): home ≠ away (also a CHECK); date within the season; both clubs enrolled in the
 * edition; no other match with the same matchday, home and away clubs (natural key, F-10). A club
 * with another match on the same day in the same competition is a warning, not a block.
 * The stadium defaults to the home stadium of the home club and is saved on the match (D-185).
 * Refused in a closed season (D-317). No warning towards the proposals (D-041).
 * Audit match.created / match.updated (only the changed fields).
 */
final class SaveMatch
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(?FixtureMatch $match, MatchData $data, User $actor): SavedMatch
    {
        return DB::transaction(function () use ($match, $data, $actor): SavedMatch {
            $edition = CompetitionSeason::query()->with(['season', 'competition'])->lockForUpdate()->findOrFail($data->competitionSeasonId);
            $season = $edition->season;

            if ($season->isReadOnly()) {
                throw SportsActionNotAllowed::seasonClosed($season->code);
            }

            if ($data->matchDate->lt($season->starts_on) || $data->matchDate->gt($season->ends_on)) {
                throw SportsActionNotAllowed::dateOutsideSeason($season->code);
            }

            $home = Club::query()->findOrFail($data->homeClubId);
            $away = Club::query()->findOrFail($data->awayClubId);
            $enrolled = $edition->enrollments()->whereIn('club_id', [$home->id, $away->id])->pluck('club_id')->all();

            foreach ([$home, $away] as $club) {
                if (! in_array($club->id, $enrolled, true)) {
                    throw SportsActionNotAllowed::clubNotEnrolled($club->name, $edition->label());
                }
            }

            $matchday = Matchday::query()->firstOrCreate([
                'competition_season_id' => $edition->id,
                'stage' => $data->stage,
                'number' => $data->matchdayNumber,
            ]);

            $duplicate = FixtureMatch::query()
                ->where('matchday_id', $matchday->id)
                ->where('home_club_id', $home->id)
                ->where('away_club_id', $away->id)
                ->when($match !== null, fn ($query) => $query->whereKeyNot($match?->id))
                ->exists();

            if ($duplicate) {
                throw SportsActionNotAllowed::matchExists();
            }

            if ($match !== null) {
                $match = FixtureMatch::query()->with(['matchday', 'homeClub', 'awayClub', 'stadium'])->lockForUpdate()->findOrFail($match->id);
                $before = SportsAuditValues::match($match);
            } else {
                $match = new FixtureMatch(['source' => MatchSource::Manual]);
                $before = null;
            }

            $match->fill([
                'matchday_id' => $matchday->id,
                'home_club_id' => $home->id,
                'away_club_id' => $away->id,
                'stadium_id' => $data->stadiumId ?? $home->home_stadium_id,
                'match_date' => $data->matchDate,
                'kickoff_time' => $data->kickoffTime,
            ]);
            $match->setRelation('matchday', $matchday->setRelation('competitionSeason', $edition));
            $match->setRelation('homeClub', $home)->setRelation('awayClub', $away);
            $match->load('stadium');

            $after = SportsAuditValues::match($match);
            $warnings = $this->warnings($match, $edition);

            if ($before === null) {
                $match->save();
                $this->audit->record(AuditAction::MatchCreated, $actor, AuditSubject::match($match), [], $after);

                return new SavedMatch($match, true, $warnings);
            }

            [$old, $new] = AuditLog::changes($before, $after);

            if ($new === []) {
                return new SavedMatch($match, false, []);
            }

            $match->save();
            $this->audit->record(AuditAction::MatchUpdated, $actor, AuditSubject::match($match), $old, $new);

            return new SavedMatch($match, true, $warnings);
        });
    }

    /**
     * M02 §8: a club cannot play two matches on the same day in the same competition (warning).
     *
     * @return list<string>
     */
    private function warnings(FixtureMatch $match, CompetitionSeason $edition): array
    {
        $warnings = [];

        foreach ([$match->homeClub, $match->awayClub] as $club) {
            $sameDay = FixtureMatch::query()
                ->whereHas('matchday', fn ($query) => $query->where('competition_season_id', $edition->id))
                ->whereDate('match_date', $match->match_date->toDateString())
                ->where(fn ($query) => $query->where('home_club_id', $club->id)->orWhere('away_club_id', $club->id))
                ->when($match->exists, fn ($query) => $query->whereKeyNot($match->id))
                ->exists();

            if ($sameDay) {
                $warnings[] = Text::get('sports.warnings.same_day', ['club' => $club->name, 'date' => $match->match_date->format('d/m/Y')]);
            }
        }

        return $warnings;
    }
}
