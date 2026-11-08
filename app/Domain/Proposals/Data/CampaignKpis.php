<?php

namespace App\Domain\Proposals\Data;

use App\Domain\Sports\Data\MatchCount;
use Illuminate\Support\Carbon;

/**
 * KPI of the campaign of a draft (M06 §6, D-093; layout-dinamici §4.4; F4-04): calculated by the server
 * with MatchCounter on all the priced clubs together, saved on the working version at every save and
 * shown in the summary. They are the same data of the document (D-111).
 *
 * - clubs: the clubs of the proposal;
 * - totalMatches: home league matches of the clubs in the period (RB-02);
 * - ledMatches: the matches of the clubs with the LED (mockup "Riepilogo"; not saved);
 * - totalSpots: Σ seconds per match / 15 × matches of the clubs with the LED (M06 §6: minutes × 4);
 * - campaign start, end and months (RB-04, D-320, D-328);
 * - top matches only in the competitions with the KPI (D-036): status as MatchCount.
 */
final readonly class CampaignKpis
{
    /** One spot every 15 seconds ("format 15 secondi cad.", layout-dinamici §4.4). */
    public const int SPOT_SECONDS = 15;

    public function __construct(
        public int $clubs,
        public int $totalMatches,
        public int $ledMatches,
        public int $totalSpots,
        public ?Carbon $campaignStart,
        public ?Carbon $campaignEnd,
        public int $campaignMonths,
        public ?int $topMatches,
        public string $topMatchesStatus,
        public bool $fromCalendar,
    ) {}

    public static function spotsPerMatch(int $seconds): int
    {
        return intdiv($seconds, self::SPOT_SECONDS);
    }

    public function topMatchesApplicable(): bool
    {
        return $this->topMatchesStatus !== MatchCount::TOP_NOT_APPLICABLE;
    }
}
