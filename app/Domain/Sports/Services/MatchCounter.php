<?php

namespace App\Domain\Sports\Services;

use App\Domain\Sports\Data\ClubMatchCount;
use App\Domain\Sports\Data\MatchCount;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\CompetitionType;
use App\Enums\MatchdayStage;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Support\Text;
use Illuminate\Support\Carbon;

/**
 * Match service (M02 F-12): given the clubs of a contract, its start and end dates, the home league
 * matches of each club in the period and the KPI of the campaign. The only implementation, used by
 * the configurator, the simulator and the documents (CLAUDE.md §4).
 *
 * - Period: from the start date to the end date, the end of the season by default (RB-03, D-039),
 *   both included and limited to the dates of the season (D-320).
 * - Each club counts in its league edition of the season (one league per club and season, D-185);
 *   a club without one stops the calculation (D-328).
 * - Calendar loaded: home matches of the regular stage, not cancelled, in the period (RB-01, RB-02,
 *   D-033). The campaign starts with the first home match of any club from the start date and its
 *   months run from that month to the month of the last match (RB-04, D-032; layout-dinamici §3).
 * - No calendar: standard number × days of the period / days of the season, rounded to the nearest
 *   whole number with .5 up, with a warning when the period is not the whole season (RB-05, D-187,
 *   D-320). If any club has no calendar, the months run from the month of the start to the month of
 *   the end of the contract (D-320, D-328).
 * - Top matches: home matches whose away club has the top match flag (RB-06, D-253), only in
 *   competitions with the KPI (D-036); not available when one of those clubs has no calendar.
 */
final class MatchCounter
{
    /**
     * @param  iterable<Club>  $clubs
     */
    public function count(Season $season, iterable $clubs, Carbon $startsOn, ?Carbon $endsOn = null): MatchCount
    {
        $periodStart = $startsOn->copy()->startOfDay()->max($season->starts_on->copy()->startOfDay());
        $periodEnd = ($endsOn ?? $season->ends_on)->copy()->startOfDay()->min($season->ends_on->copy()->startOfDay());

        if ($periodEnd->lt($periodStart)) {
            throw SportsActionNotAllowed::periodOutsideSeason($season->code);
        }

        $counts = [];
        $warnings = [];

        foreach ($clubs as $club) {
            $edition = $this->leagueEdition($season, $club);
            $count = $edition->calendar_loaded_at !== null
                ? $this->fromCalendar($club, $edition, $periodStart, $periodEnd)
                : $this->fromStandard($club, $edition, $season, $periodStart, $periodEnd);

            if ($count->prorated) {
                $warnings[] = Text::get('sports.match_service.prorated', [
                    'club' => $club->name,
                    'matches' => (string) $count->matches,
                    'standard' => (string) $edition->standard_home_matches,
                ]);
            }

            $counts[] = $count;
        }

        [$campaignStart, $campaignEnd] = $this->campaign($counts, $periodStart, $periodEnd);
        [$topMatches, $topStatus] = $this->topMatches($counts);

        return new MatchCount(
            $periodStart,
            $periodEnd,
            $counts,
            array_sum(array_map(fn (ClubMatchCount $count): int => $count->matches, $counts)),
            $campaignStart,
            $campaignEnd,
            $campaignStart !== null && $campaignEnd !== null ? self::months($campaignStart, $campaignEnd) : 0,
            $topMatches,
            $topStatus,
            $warnings,
        );
    }

    /**
     * D-185: the league edition of the season in which the club is enrolled (D-328: required).
     */
    private function leagueEdition(Season $season, Club $club): CompetitionSeason
    {
        $edition = CompetitionSeason::query()
            ->where('season_id', $season->id)
            ->whereHas('competition', fn ($query) => $query->where('type', CompetitionType::League->value))
            ->whereHas('enrollments', fn ($query) => $query->where('club_id', $club->id))
            ->with('competition')
            ->first();

        if ($edition === null) {
            throw SportsActionNotAllowed::clubNotInLeague($club->name, $season->code);
        }

        return $edition;
    }

    private function fromCalendar(Club $club, CompetitionSeason $edition, Carbon $start, Carbon $end): ClubMatchCount
    {
        $matches = FixtureMatch::query()
            ->join('matchdays', 'matchdays.id', '=', 'matches.matchday_id')
            ->where('matchdays.competition_season_id', $edition->id)
            ->where('matchdays.stage', MatchdayStage::Regular->value)
            ->where('matches.home_club_id', $club->id)
            ->where('matches.status', MatchStatus::Scheduled->value)
            ->whereBetween('matches.match_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('matches.match_date')
            ->select('matches.*')
            ->with('awayClub')
            ->get();

        $topMatches = $edition->competition->has_top_match_kpi
            ? $matches->filter(fn (FixtureMatch $match): bool => $match->awayClub->is_top_match_opponent)->count()
            : null;

        return new ClubMatchCount(
            $club,
            $edition,
            true,
            $matches->count(),
            $topMatches,
            $matches->first()?->match_date,
            $matches->last()?->match_date,
            false,
        );
    }

    /**
     * RB-05, D-187, D-320: standard × days of the period / days of the season, both counted with the
     * first and the last day, rounded to the nearest whole number with .5 up, in integer arithmetic.
     */
    private function fromStandard(Club $club, CompetitionSeason $edition, Season $season, Carbon $start, Carbon $end): ClubMatchCount
    {
        $periodDays = self::days($start, $end);
        $seasonDays = self::days($season->starts_on, $season->ends_on);
        $standard = $edition->standard_home_matches;
        $matches = intdiv(2 * $standard * $periodDays + $seasonDays, 2 * $seasonDays);

        return new ClubMatchCount($club, $edition, false, $matches, null, null, null, $periodDays !== $seasonDays);
    }

    /**
     * RB-04, D-032, D-320, D-328: from the first to the last home match of the clubs when every
     * calendar is loaded, otherwise from the start to the end of the contract.
     *
     * @param  list<ClubMatchCount>  $counts
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function campaign(array $counts, Carbon $periodStart, Carbon $periodEnd): array
    {
        $first = null;
        $last = null;

        foreach ($counts as $count) {
            if (! $count->fromCalendar) {
                return [$periodStart, $periodEnd];
            }

            if ($count->firstMatch !== null && ($first === null || $count->firstMatch->lt($first))) {
                $first = $count->firstMatch;
            }

            if ($count->lastMatch !== null && ($last === null || $count->lastMatch->gt($last))) {
                $last = $count->lastMatch;
            }
        }

        return [$first, $last];
    }

    /**
     * RB-06, D-036, D-320, D-328.
     *
     * @param  list<ClubMatchCount>  $counts
     * @return array{0: int|null, 1: string}
     */
    private function topMatches(array $counts): array
    {
        $withKpi = array_filter($counts, fn (ClubMatchCount $count): bool => $count->hasTopMatchKpi());

        if ($withKpi === []) {
            return [null, MatchCount::TOP_NOT_APPLICABLE];
        }

        $total = 0;

        foreach ($withKpi as $count) {
            if ($count->topMatches === null) {
                return [null, MatchCount::TOP_NOT_AVAILABLE];
            }

            $total += $count->topMatches;
        }

        return [$total, MatchCount::TOP_AVAILABLE];
    }

    /**
     * Days from one date to another, both included (D-320).
     */
    private static function days(Carbon $from, Carbon $to): int
    {
        return (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
    }

    /**
     * Calendar months from the month of one date to the month of another, both included:
     * August 2026 → May 2027 = 10 (layout-dinamici §3).
     */
    public static function months(Carbon $from, Carbon $to): int
    {
        return ((int) $to->format('Y') * 12 + (int) $to->format('n')) - ((int) $from->format('Y') * 12 + (int) $from->format('n')) + 1;
    }
}
