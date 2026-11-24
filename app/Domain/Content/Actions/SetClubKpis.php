<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Content\Data\KpiInput;
use App\Domain\Content\Exceptions\ContentActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\Club;
use App\Models\ClubKpiValue;
use App\Models\KpiDefinition;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * KPI and digital of a club in a season, saved together from its page (M08 F-05, FL-02; D-109, D-203,
 * D-491): admin and manager.
 *
 * - Only Infront clubs enrolled in a competition of the season (the clubs of the price list grid) and
 *   only active definitions; refused in a closed season (D-317).
 * - A value with source and period is created or updated; a null value removes the row (D-491).
 * - updated_at and updated_by change only when value, source or period change (D-491, D-492): an
 *   untouched KPI keeps the date of its last update. Nothing is written when nothing changes.
 * - One audit event club_kpis.updated per save with the changed KPI only: "<code>" with value ·
 *   source · period, null when removed.
 *
 * Returns the number of KPI changed.
 */
final class SetClubKpis
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PriceListGrid $grid,
    ) {}

    /**
     * @param  list<KpiInput>  $inputs
     */
    public function handle(Season $season, Club $club, array $inputs, User $actor): int
    {
        if ($season->isReadOnly()) {
            throw ContentActionNotAllowed::kpisSeasonClosed($season->code);
        }

        if (! $this->grid->hasClub($season, $club)) {
            throw ContentActionNotAllowed::clubNotInSeason($club->name, $season->code);
        }

        return DB::transaction(function () use ($season, $club, $inputs, $actor): int {
            $definitions = KpiDefinition::query()->where('is_active', true)->get()->keyBy('id');
            $rows = ClubKpiValue::query()
                ->where('season_id', $season->id)
                ->where('club_id', $club->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('kpi_definition_id');

            $before = [];
            $after = [];

            foreach ($inputs as $input) {
                $definition = $definitions->get($input->definitionId);

                if ($definition === null) {
                    throw ContentActionNotAllowed::kpiNotActive();
                }

                $row = $rows->get($definition->id);
                $field = strtolower($definition->code);
                $old = $row !== null ? self::describe($row->value, $row->source, $row->reference_period) : null;

                if ($input->value === null) {
                    if ($row === null) {
                        continue;
                    }

                    $row->delete();
                    $before[$field] = $old;
                    $after[$field] = null;

                    continue;
                }

                $source = trim((string) $input->source);
                $period = trim((string) $input->period);
                $new = self::describe($input->value, $source, $period);

                if ($old === $new) {
                    continue;
                }

                $values = ['value' => $input->value, 'source' => $source, 'reference_period' => $period, 'updated_by' => $actor->id];

                if ($row === null) {
                    ClubKpiValue::query()->create([
                        'club_id' => $club->id,
                        'season_id' => $season->id,
                        'kpi_definition_id' => $definition->id,
                        ...$values,
                    ]);
                } else {
                    $row->fill($values)->save();
                }

                $before[$field] = $old;
                $after[$field] = $new;
            }

            if ($after === []) {
                return 0;
            }

            $this->audit->record(
                AuditAction::ClubKpisUpdated,
                $actor,
                AuditSubject::clubKpis($club, $season),
                array_filter($before, fn (?string $value): bool => $value !== null),
                $after,
            );

            return count($after);
        });
    }

    /** "1250000 · Fonte · Periodo": one string per KPI in the audit (D-284). */
    private static function describe(int $value, string $source, string $period): string
    {
        return $value.' · '.$source.' · '.$period;
    }
}
