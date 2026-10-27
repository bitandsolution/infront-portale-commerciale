<?php

namespace App\Domain\Sports\Data;

use App\Models\Club;
use App\Models\CompetitionSeason;
use Illuminate\Support\Carbon;

/**
 * Home league matches of one club in the period of a contract (F-12): counted on the calendar when
 * it is loaded (RB-01, RB-02), otherwise estimated from the standard number of the competition
 * (RB-05, D-187, D-320). Top matches only where the competition has the KPI (RB-06, D-036) and
 * only on a loaded calendar; null otherwise.
 */
final readonly class ClubMatchCount
{
    public function __construct(
        public Club $club,
        public CompetitionSeason $edition,
        public bool $fromCalendar,
        public int $matches,
        public ?int $topMatches,
        public ?Carbon $firstMatch,
        public ?Carbon $lastMatch,
        public bool $prorated,
    ) {}

    public function hasTopMatchKpi(): bool
    {
        return $this->edition->competition->has_top_match_kpi;
    }
}
