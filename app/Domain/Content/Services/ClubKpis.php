<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Data\ClubKpiFigures;
use App\Domain\Content\Data\ClubKpiSet;
use App\Domain\Content\Data\KpiFigure;
use App\Models\Club;
use App\Models\ClubKpiValue;
use App\Models\KpiDefinition;
use App\Models\Season;

/**
 * KPI in force of the clubs of a document (M08 F-06, RB-04, RB-05; D-251, D-490, D-493,
 * D-500…D-502; F6-03): the values of the active definitions in the season of the group, read when the
 * document is composed. Used by DocumentComposer (warnings of the composition, the simulator, the
 * snapshot of DocumentBuilder) and, with F7, by the layouts of the KPI: one implementation.
 */
final class ClubKpis
{
    /**
     * @param  array<int, int|null>  $clubs  club id → tier of its competition in the proposal, in the order of the document
     */
    public function of(Season $season, array $clubs): ClubKpiSet
    {
        /** @var list<KpiDefinition> $definitions */
        $definitions = array_values(KpiDefinition::query()->activeOrdered()->get()->all());
        $ids = array_keys($clubs);

        if ($ids === []) {
            return new ClubKpiSet($definitions, []);
        }

        /** @var array<int, string> $names */
        $names = Club::query()->whereKey($ids)->pluck('name', 'id')->all();
        $codes = [];

        foreach ($definitions as $definition) {
            $codes[$definition->id] = $definition;
        }

        $values = [];

        foreach (ClubKpiValue::query()
            ->where('season_id', $season->id)
            ->whereIn('club_id', $ids)
            ->whereIn('kpi_definition_id', array_keys($codes))
            ->get() as $row) {
            $definition = $codes[$row->kpi_definition_id];
            $values[$row->club_id][$definition->code] = new KpiFigure(
                code: $definition->code,
                name: $definition->name,
                value: $row->value,
                source: $row->source,
                period: $row->reference_period,
                updatedAt: $row->updated_at,
            );
        }

        $figures = [];

        foreach ($clubs as $clubId => $tier) {
            $kpis = [];

            // In the order of the definitions, whatever the order of the rows.
            foreach ($definitions as $definition) {
                if (isset($values[$clubId][$definition->code])) {
                    $kpis[$definition->code] = $values[$clubId][$definition->code];
                }
            }

            $figures[$clubId] = new ClubKpiFigures($clubId, $names[$clubId] ?? (string) $clubId, $tier, $kpis);
        }

        return new ClubKpiSet($definitions, $figures);
    }
}
