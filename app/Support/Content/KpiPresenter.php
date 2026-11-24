<?php

namespace App\Support\Content;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\KpiGroup;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\ClubKpiValue;
use App\Models\KpiDefinition;
use App\Models\Season;
use App\Support\Inventory\InventoryPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Props of the pages "Contenuti › KPI e digital" (M08 F-05, F-08; D-491, D-492; F6-01): the list of
 * the Infront clubs of the season with how many KPI they have and how many are not updated for more
 * than 6 months, and the form of a club with one section per group. Values written with the Italian
 * separators of the thousands; system timestamps in Europe/Rome (C7).
 */
final class KpiPresenter
{
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(private readonly PriceListGrid $grid) {}

    /**
     * @return array<string, mixed>
     */
    public function listPage(Season $season): array
    {
        $clubs = $this->grid->clubs($season);
        $definitions = KpiDefinition::query()->activeOrdered()->get();
        $values = ClubKpiValue::query()
            ->where('season_id', $season->id)
            ->whereIn('kpi_definition_id', $definitions->modelKeys())
            ->get()
            ->groupBy('club_id');
        $now = Carbon::now();

        return [
            'season' => self::season($season),
            'total' => $definitions->count(),
            'staleMonths' => ClubKpiValue::STALE_MONTHS,
            'competitions' => InventoryPresenter::competitions($clubs),
            'rows' => $clubs->map(function (Club $club) use ($season, $values, $now): array {
                /** @var Collection<int, ClubKpiValue> $clubValues */
                $clubValues = $values->get($club->id, collect());
                $last = $clubValues->max('updated_at');

                return [
                    'clubId' => $club->id,
                    'name' => $club->name,
                    'competitionIds' => $club->enrollments
                        ->map(fn (ClubEnrollment $enrollment): int => $enrollment->competitionSeason->competition_id)
                        ->unique()->values()->all(),
                    'present' => $clubValues->count(),
                    'stale' => $clubValues->filter(fn (ClubKpiValue $value): bool => $value->isStale($now))->count(),
                    'lastUpdate' => $last instanceof Carbon ? self::date($last) : null,
                    'editUrl' => route('content.kpis.edit', [$club, 'stagione' => $season->id]),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clubPage(Season $season, Club $club): array
    {
        $definitions = KpiDefinition::query()->activeOrdered()->get();
        $values = ClubKpiValue::query()
            ->with('updater')
            ->where('season_id', $season->id)
            ->where('club_id', $club->id)
            ->get()
            ->keyBy('kpi_definition_id');
        $now = Carbon::now();

        return [
            'season' => self::season($season),
            'club' => ['id' => $club->id, 'name' => $club->name],
            'staleMonths' => ClubKpiValue::STALE_MONTHS,
            'groups' => array_map(fn (KpiGroup $group): array => [
                'group' => $group->value,
                'label' => $group->label(),
                'rows' => $definitions
                    ->filter(fn (KpiDefinition $definition): bool => $definition->group === $group)
                    ->map(function (KpiDefinition $definition) use ($values, $now): array {
                        $value = $values->get($definition->id);

                        return [
                            'definitionId' => $definition->id,
                            'name' => $definition->name,
                            'unit' => $definition->unit->label(),
                            'networkAggregate' => $definition->network_aggregate,
                            'value' => $value !== null ? self::number($value->value) : '',
                            'source' => $value !== null ? $value->source : '',
                            'period' => $value !== null ? $value->reference_period : '',
                            'updatedAt' => $value !== null ? self::date($value->updated_at) : null,
                            'updatedBy' => $value?->updater?->fullName(),
                            'isStale' => $value !== null && $value->isStale($now),
                        ];
                    })->values()->all(),
            ], KpiGroup::cases()),
            'saveUrl' => route('content.kpis.update', $club),
            'backUrl' => route('pages.content_kpi'),
        ];
    }

    /** 1250000 → "1.250.000" (Italian separators of the thousands). */
    public static function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /**
     * @return array{id: int, code: string, readOnly: bool}
     */
    private static function season(Season $season): array
    {
        return ['id' => $season->id, 'code' => $season->code, 'readOnly' => $season->isReadOnly()];
    }

    private static function date(Carbon $moment): string
    {
        return $moment->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y');
    }
}
