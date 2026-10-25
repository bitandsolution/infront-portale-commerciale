<?php

namespace App\Domain\Sports\Data;

use App\Enums\CompetitionType;

/**
 * Data of a competition from the Competitions page (M02 F-02). The code is used only on creation:
 * it is a stable identifier (C3) and does not change afterwards.
 */
final readonly class CompetitionData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $shortName,
        public CompetitionType $type,
        public int $tier,
        public bool $hasTopMatchKpi,
        public bool $isActive,
        public int $sortOrder,
    ) {}
}
