<?php

namespace App\Support\Sports;

use App\Domain\Sports\Data\CalendarPreview;
use App\Enums\CalendarImportStatus;
use App\Enums\CompetitionType;
use App\Enums\SeasonStatus;
use App\Models\CalendarImport;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Models\Stadium;
use Illuminate\Support\Carbon;

/**
 * Sports registry as the pages show it (M02): Italian labels from lang/it/sports.php, sports dates
 * as dd/mm/yyyy (C7: local dates, no conversion).
 *
 * @phpstan-import-type PreviewRow from CalendarPreview
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
     * @return array{id: int, competitionCode: string, competitionName: string, competitionActive: bool, standardHomeMatches: int, calendarLoadedAt: string|null, enrollments: int, showUrl: string, updateUrl: string, destroyUrl: string}
     */
    public static function editionRow(CompetitionSeason $edition): array
    {
        return [
            'id' => $edition->id,
            'competitionCode' => $edition->competition->code,
            'competitionName' => $edition->competition->name,
            'competitionActive' => $edition->competition->is_active,
            'standardHomeMatches' => $edition->standard_home_matches,
            'calendarLoadedAt' => self::dateTime($edition->calendar_loaded_at),
            'enrollments' => $edition->enrollments()->count(),
            'showUrl' => route('sports.editions.show', $edition),
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

    /**
     * @return array{id: int, stage: string, stageLabel: string, matchday: int, date: string, kickoff: string|null, homeClub: string, awayClub: string, stadium: string|null, status: string, statusLabel: string, source: string, editUrl: string, cancelUrl: string, restoreUrl: string}
     */
    public static function matchRow(FixtureMatch $match): array
    {
        return [
            'id' => $match->id,
            'stage' => $match->matchday->stage->value,
            'stageLabel' => $match->matchday->stage->label(),
            'matchday' => $match->matchday->number,
            'date' => self::date($match->match_date),
            'kickoff' => $match->kickoff(),
            'homeClub' => $match->homeClub->short_name,
            'awayClub' => $match->awayClub->short_name,
            'stadium' => $match->stadium?->name,
            'status' => $match->status->value,
            'statusLabel' => $match->status->label(),
            'source' => $match->source->value,
            'editUrl' => route('sports.matches.edit', $match),
            'cancelUrl' => route('sports.matches.cancel', $match),
            'restoreUrl' => route('sports.matches.restore', $match),
        ];
    }

    /**
     * Editions of a season for a select, in the order of the competitions.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function editionOptions(Season $season): array
    {
        return array_values(CompetitionSeason::query()
            ->where('season_id', $season->id)
            ->with('competition')
            ->get()
            ->sortBy(fn (CompetitionSeason $edition): array => [$edition->competition->sort_order, $edition->competition->name])
            ->map(fn (CompetitionSeason $edition): array => ['value' => (string) $edition->id, 'label' => $edition->competition->name])
            ->values()
            ->all());
    }

    /**
     * A calendar import in the list and in the header of its page (FL-02).
     *
     * @return array{id: int, edition: string, competition: string, fileName: string, fileUrl: string, uploadedBy: string, uploadedAt: string|null, status: string, statusLabel: string, statusTone: string, inserted: int, updated: int, unchanged: int, discarded: int, confirmedAt: string|null, showUrl: string}
     */
    public static function importRow(CalendarImport $import): array
    {
        return [
            'id' => $import->id,
            'edition' => $import->competitionSeason->label(),
            'competition' => $import->competitionSeason->competition->name,
            'fileName' => $import->file->original_name ?? $import->file->downloadName(),
            'fileUrl' => route('files.download', $import->file_id),
            'uploadedBy' => $import->uploader->fullName(),
            'uploadedAt' => self::dateTime($import->created_at),
            'status' => $import->status->value,
            'statusLabel' => $import->status->label(),
            'statusTone' => match ($import->status) {
                CalendarImportStatus::Confirmed => 'success',
                CalendarImportStatus::Preview => 'notice',
                CalendarImportStatus::Discarded => 'muted',
            },
            'inserted' => $import->inserted_count,
            'updated' => $import->updated_count,
            'unchanged' => $import->unchanged_count,
            'discarded' => $import->discarded_count,
            'confirmedAt' => self::dateTime($import->confirmed_at),
            'showUrl' => route('sports.imports.show', $import),
        ];
    }

    /**
     * A row of the preview (FL-02 step 2), with the date as dd/mm/yyyy.
     *
     * @param  PreviewRow  $row
     * @return array{line: int, matchday: int|null, date: string|null, time: string|null, home: string, away: string, stadium: string, outcome: string, unknown: bool, errors: list<string>, warnings: list<string>, changes: list<string>}
     */
    public static function importPreviewRow(array $row): array
    {
        return [
            'line' => $row['line'],
            'matchday' => $row['matchday'],
            'date' => $row['date'] !== null ? Carbon::parse($row['date'])->format('d/m/Y') : null,
            'time' => $row['time'],
            'home' => $row['home'],
            'away' => $row['away'],
            'stadium' => $row['stadium'],
            'outcome' => $row['outcome'],
            'unknown' => $row['unknown'],
            'errors' => $row['errors'],
            'warnings' => $row['warnings'],
            'changes' => $row['changes'],
        ];
    }

    public static function date(Carbon $date): string
    {
        return $date->format('d/m/Y');
    }

    /**
     * System timestamp (UTC) shown in Europe/Rome (C7).
     */
    public static function dateTime(?Carbon $moment): ?string
    {
        return $moment?->copy()->setTimezone('Europe/Rome')->format('d/m/Y H:i');
    }
}
