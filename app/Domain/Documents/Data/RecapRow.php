<?php

namespace App\Domain\Documents\Data;

/**
 * One row of the economic recap (layout-dinamici §4.8): a club, or the hospitality line under its
 * club (secondary row, smaller text, with its own price). Prices are list prices (D-103) as
 * decimal strings (C5). Null LED values are written as "—" (club without LED).
 */
final readonly class RecapRow
{
    public function __construct(
        public string $label,
        public ?string $logoPath,
        public string $format,
        public ?int $matches,
        public ?int $secondsPerMatch,
        public ?int $spotsPerMatch,
        public string $listPrice,
        public bool $isHospitality = false,
    ) {}
}
