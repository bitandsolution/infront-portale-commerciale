<?php

namespace App\Domain\Content\Data;

/**
 * The KPI in force of a club of a document (M08 §5; D-491, D-500): its name, the tier of the
 * competition of the club in the proposal (for the scope of D-251) and its values by KPI code; a KPI
 * without a value is not in $kpis.
 */
final readonly class ClubKpiFigures
{
    /**
     * @param  array<string, KpiFigure>  $kpis  KPI code → value in force
     */
    public function __construct(
        public int $clubId,
        public string $clubName,
        public ?int $competitionTier,
        public array $kpis,
    ) {}

    public function has(string $code): bool
    {
        return isset($this->kpis[$code]);
    }
}
