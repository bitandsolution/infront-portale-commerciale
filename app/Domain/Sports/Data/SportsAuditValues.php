<?php

namespace App\Domain\Sports\Data;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;

/**
 * Fields of the sports registry recorded in the audit events (D-127, D-284): plain values, dates
 * as Y-m-d, enums as their stored value. AuditLog::changes() keeps only the changed ones.
 * The codes are recorded as season_code / competition_code: AuditLog never records a field named
 * "code" (the code of the second factor).
 */
final class SportsAuditValues
{
    /**
     * @return array<string, string>
     */
    public static function season(Season $season): array
    {
        return [
            'season_code' => $season->code,
            'name' => $season->name,
            'starts_on' => $season->starts_on->toDateString(),
            'ends_on' => $season->ends_on->toDateString(),
            'status' => $season->status->value,
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function competition(Competition $competition): array
    {
        return [
            'competition_code' => $competition->code,
            'name' => $competition->name,
            'short_name' => $competition->short_name,
            'type' => $competition->type->value,
            'tier' => $competition->tier,
            'has_top_match_kpi' => $competition->has_top_match_kpi,
            'is_active' => $competition->is_active,
            'sort_order' => $competition->sort_order,
        ];
    }

    /**
     * @return array<string, string|int>
     */
    public static function edition(CompetitionSeason $edition): array
    {
        return [
            'competition' => $edition->competition->code,
            'season' => $edition->season->code,
            'standard_home_matches' => $edition->standard_home_matches,
        ];
    }
}
