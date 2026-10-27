<?php

namespace App\Domain\Sports\Data;

use Illuminate\Support\Carbon;

/**
 * Result of the match service (F-12) for the clubs of a contract, as the configurator and the
 * documents use it (KPI of the campaign, D-093): matches of each club and in total, start and
 * months of the campaign (RB-04, D-320, D-328), top matches (RB-06, D-253).
 *
 * topMatchesStatus: "available" (a number), "not_available" (a calendar is missing: "n.d." in the
 * documents, D-320, D-328) or "not_applicable" (no club of the contract plays a competition with the
 * top match KPI, D-036).
 */
final readonly class MatchCount
{
    public const string TOP_AVAILABLE = 'available';

    public const string TOP_NOT_AVAILABLE = 'not_available';

    public const string TOP_NOT_APPLICABLE = 'not_applicable';

    /**
     * @param  list<ClubMatchCount>  $clubs
     * @param  list<string>  $warnings  for the agent (RB-05: estimate from the standard number)
     */
    public function __construct(
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public array $clubs,
        public int $totalMatches,
        public ?Carbon $campaignStart,
        public ?Carbon $campaignEnd,
        public int $campaignMonths,
        public ?int $topMatches,
        public string $topMatchesStatus,
        public array $warnings,
    ) {}

    /**
     * True when every club is counted on a loaded calendar.
     */
    public function fromCalendar(): bool
    {
        foreach ($this->clubs as $club) {
            if (! $club->fromCalendar) {
                return false;
            }
        }

        return true;
    }
}
