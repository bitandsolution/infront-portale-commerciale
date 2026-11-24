<?php

namespace App\Domain\Content\Data;

/**
 * A KPI of the form of a club (F6-01; D-491): the definition and its value with source and period;
 * a null value removes the KPI (no row), and then source and period are ignored.
 */
final readonly class KpiInput
{
    public function __construct(
        public int $definitionId,
        public ?int $value,
        public ?string $source,
        public ?string $period,
    ) {}
}
