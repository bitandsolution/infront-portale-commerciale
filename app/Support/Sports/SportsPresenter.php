<?php

namespace App\Support\Sports;

use App\Enums\CompetitionType;
use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use Illuminate\Support\Carbon;

/**
 * Sports registry as the pages show it (M02): Italian labels from lang/it/sports.php, sports dates
 * as dd/mm/yyyy (C7: local dates, no conversion).
 */
final class SportsPresenter
{
    /**
     * @return array{id: int, code: string, name: string, startsOn: string, endsOn: string, status: string, statusLabel: string, competitions: int, url: string}
     */
    public static function seasonRow(Season $season, int $competitions): array
    {
        return [
            'id' => $season->id,
            'code' => $season->code,
            'name' => $season->name,
            'startsOn' => self::date($season->starts_on),
            'endsOn' => self::date($season->ends_on),
            'status' => $season->status->value,
            'statusLabel' => $season->status->label(),
            'competitions' => $competitions,
            'url' => route('sports.seasons.edit', $season),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, shortName: string, type: string, typeLabel: string, tier: int, hasTopMatchKpi: bool, isActive: bool, sortOrder: int, seasons: int, url: string}
     */
    public static function competitionRow(Competition $competition, int $seasons): array
    {
        return [
            'id' => $competition->id,
            'code' => $competition->code,
            'name' => $competition->name,
            'shortName' => $competition->short_name,
            'type' => $competition->type->value,
            'typeLabel' => $competition->type->label(),
            'tier' => $competition->tier,
            'hasTopMatchKpi' => $competition->has_top_match_kpi,
            'isActive' => $competition->is_active,
            'sortOrder' => $competition->sort_order,
            'seasons' => $seasons,
            'url' => route('sports.competitions.edit', $competition),
        ];
    }

    /**
     * @return array{id: int, competitionCode: string, competitionName: string, competitionActive: bool, standardHomeMatches: int, calendarLoadedAt: string|null, updateUrl: string, destroyUrl: string}
     */
    public static function editionRow(CompetitionSeason $edition): array
    {
        return [
            'id' => $edition->id,
            'competitionCode' => $edition->competition->code,
            'competitionName' => $edition->competition->name,
            'competitionActive' => $edition->competition->is_active,
            'standardHomeMatches' => $edition->standard_home_matches,
            'calendarLoadedAt' => $edition->calendar_loaded_at?->copy()->setTimezone('Europe/Rome')->format('d/m/Y H:i'),
            'updateUrl' => route('sports.editions.update', $edition),
            'destroyUrl' => route('sports.editions.destroy', $edition),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function seasonStatusOptions(): array
    {
        return array_map(
            fn (SeasonStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            SeasonStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function competitionTypeOptions(): array
    {
        return array_map(
            fn (CompetitionType $type): array => ['value' => $type->value, 'label' => $type->label()],
            CompetitionType::cases(),
        );
    }

    public static function date(Carbon $date): string
    {
        return $date->format('d/m/Y');
    }
}
