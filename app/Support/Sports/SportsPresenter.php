<?php

namespace App\Support\Sports;

use App\Enums\CompetitionType;
use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\Stadium;
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

    /**
     * @return array{id: int, name: string, city: string, capacity: int|null, isActive: bool, clubs: int, url: string}
     */
    public static function stadiumRow(Stadium $stadium, int $clubs): array
    {
        return [
            'id' => $stadium->id,
            'name' => $stadium->name,
            'city' => $stadium->city,
            'capacity' => $stadium->capacity,
            'isActive' => $stadium->is_active,
            'clubs' => $clubs,
            'url' => route('sports.stadiums.edit', $stadium),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, shortName: string, homeStadiumId: int|null, homeStadium: string|null, primaryColor: string|null, secondaryColor: string|null, isInfront: bool, isTopMatchOpponent: bool, isActive: bool, logoUrl: string|null, url: string}
     */
    public static function clubRow(Club $club): array
    {
        return [
            'id' => $club->id,
            'code' => $club->code,
            'name' => $club->name,
            'shortName' => $club->short_name,
            'homeStadiumId' => $club->home_stadium_id,
            'homeStadium' => $club->homeStadium?->name,
            'primaryColor' => $club->primary_color,
            'secondaryColor' => $club->secondary_color,
            'isInfront' => $club->is_infront,
            'isTopMatchOpponent' => $club->is_top_match_opponent,
            'isActive' => $club->is_active,
            'logoUrl' => $club->logo_file_id !== null ? route('files.download', $club->logo_file_id) : null,
            'url' => route('sports.clubs.edit', $club),
        ];
    }

    /**
     * @return array{id: int, alias: string, source: string, sourceLabel: string, destroyUrl: string}
     */
    public static function aliasRow(ClubAlias $alias): array
    {
        return [
            'id' => $alias->id,
            'alias' => $alias->alias,
            'source' => $alias->source->value,
            'sourceLabel' => $alias->source->label(),
            'destroyUrl' => route('sports.clubs.aliases.destroy', $alias),
        ];
    }

    /**
     * Stadiums for a select: the active ones, plus the one already chosen even if deactivated.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function stadiumOptions(?int $selectedId = null): array
    {
        return array_values(Stadium::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $selectedId))
            ->orderBy('name')
            ->get()
            ->map(fn (Stadium $stadium): array => ['value' => (string) $stadium->id, 'label' => $stadium->name.' ('.$stadium->city.')'])
            ->all());
    }

    public static function date(Carbon $date): string
    {
        return $date->format('d/m/Y');
    }
}
