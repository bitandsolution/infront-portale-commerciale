<?php

namespace App\Domain\Content\Data;

use App\Enums\DocumentType;
use App\Models\KpiDefinition;

/**
 * The KPI in force of the clubs of a document (ClubKpis; M08 F-06, RB-04, RB-05; D-251, D-490,
 * D-493, D-500…D-502): the active definitions, the values of every club, the network sums and the
 * clubs without the KPI their document type uses. The only implementation of these sums: snapshot,
 * warnings and, with F7, the layouts of the KPI read them from here.
 */
final readonly class ClubKpiSet
{
    /** D-500: version of the form of the KPI snapshot of a document. */
    public const SNAPSHOT_VERSION = 2;

    /** D-501: key of the sums over all the clubs of the document. */
    public const ALL_CLUBS = 'all';

    /**
     * @param  list<KpiDefinition>  $definitions  active, in the order of the pages
     * @param  array<int, ClubKpiFigures>  $clubs  club id → KPI, in the order of the document
     */
    public function __construct(
        public array $definitions,
        public array $clubs,
    ) {}

    /**
     * Definitions summed in the network document (D-490).
     *
     * @return list<KpiDefinition>
     */
    public function aggregated(): array
    {
        return array_values(array_filter($this->definitions, fn (KpiDefinition $definition): bool => $definition->network_aggregate));
    }

    /**
     * Definitions a document type uses (D-502): all for the club document, the aggregated ones for the
     * network document.
     *
     * @return list<KpiDefinition>
     */
    public function usedBy(DocumentType $type): array
    {
        return $type === DocumentType::Network ? $this->aggregated() : $this->definitions;
    }

    /**
     * Network sums of the aggregated KPI (M08 F-06, RB-05; D-501): of all the clubs, or with a tier only
     * of the clubs of that competition (D-251). Only the clubs with the value are summed.
     *
     * @return array<string, NetworkKpiSum> KPI code → sum
     */
    public function network(?int $tier = null): array
    {
        $clubs = array_filter($this->clubs, fn (ClubKpiFigures $club): bool => $tier === null || $club->competitionTier === $tier);
        $sums = [];

        foreach ($this->aggregated() as $definition) {
            $sum = 0;
            $summed = [];
            $missing = [];

            foreach ($clubs as $club) {
                $figure = $club->kpis[$definition->code] ?? null;

                if ($figure === null) {
                    $missing[] = $club->clubId;

                    continue;
                }

                $sum += $figure->value;
                $summed[] = $club->clubId;
            }

            $sums[$definition->code] = new NetworkKpiSum($definition->code, $definition->name, $sum, $summed, $missing);
        }

        return $sums;
    }

    /**
     * Tiers of the competitions of the clubs, from the highest (1 = Serie A).
     *
     * @return list<int>
     */
    public function tiers(): array
    {
        $tiers = [];

        foreach ($this->clubs as $club) {
            if ($club->competitionTier !== null) {
                $tiers[$club->competitionTier] = true;
            }
        }

        $list = array_keys($tiers);
        sort($list);

        return $list;
    }

    /**
     * Clubs without some of the KPI the document type uses (D-502), in the order of the document.
     *
     * @return list<ClubMissingKpis>
     */
    public function missing(DocumentType $type): array
    {
        $used = $this->usedBy($type);
        $missing = [];

        foreach ($this->clubs as $club) {
            $names = [];

            foreach ($used as $definition) {
                if (! $club->has($definition->code)) {
                    $names[] = $definition->name;
                }
            }

            if ($names !== []) {
                $missing[] = new ClubMissingKpis($club->clubId, $club->clubName, $names, count($used));
            }
        }

        return $missing;
    }

    /**
     * The clubs and network parts of the KPI snapshot of a document (D-500, D-501): every club with its
     * KPI in force and the codes of those missing, the sums of all the clubs and of every tier present (PHP keeps the tiers as integer keys).
     *
     * @return array{clubs: array<int, array<string, mixed>>, network: array<int|string, array<string, array<string, mixed>>>}
     */
    public function snapshot(): array
    {
        $clubs = [];

        foreach ($this->clubs as $club) {
            $clubs[$club->clubId] = [
                'name' => $club->clubName,
                'competition_tier' => $club->competitionTier,
                'kpis' => array_map(fn (KpiFigure $figure): array => $figure->toSnapshot(), $club->kpis),
                'missing' => array_values(array_map(
                    fn (KpiDefinition $definition): string => $definition->code,
                    array_filter($this->definitions, fn (KpiDefinition $definition): bool => ! $club->has($definition->code)),
                )),
            ];
        }

        $network = [self::ALL_CLUBS => self::sums($this->network())];

        foreach ($this->tiers() as $tier) {
            $network[(string) $tier] = self::sums($this->network($tier));
        }

        return ['clubs' => $clubs, 'network' => $network];
    }

    /**
     * @param  array<string, NetworkKpiSum>  $sums
     * @return array<string, array<string, mixed>>
     */
    private static function sums(array $sums): array
    {
        return array_map(fn (NetworkKpiSum $sum): array => $sum->toSnapshot(), $sums);
    }
}
