<?php

namespace App\Domain\Sports\Data;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\Stadium;

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

    /**
     * @return array<string, string|int|bool|null>
     */
    public static function stadium(Stadium $stadium): array
    {
        return [
            'name' => $stadium->name,
            'city' => $stadium->city,
            'capacity' => $stadium->capacity,
            'is_active' => $stadium->is_active,
        ];
    }

    /**
     * The home stadium is recorded by name, readable also after a rename; the logo has its own event.
     *
     * @return array<string, string|bool|null>
     */
    public static function club(Club $club): array
    {
        return [
            'club_code' => $club->code,
            'name' => $club->name,
            'short_name' => $club->short_name,
            'home_stadium' => $club->home_stadium_id !== null ? Stadium::query()->find($club->home_stadium_id)?->name : null,
            'primary_color' => $club->primary_color,
            'secondary_color' => $club->secondary_color,
            'is_infront' => $club->is_infront,
            'is_top_match_opponent' => $club->is_top_match_opponent,
            'is_active' => $club->is_active,
        ];
    }
}
