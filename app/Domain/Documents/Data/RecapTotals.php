<?php

namespace App\Domain\Documents\Data;

/**
 * "Totale / stagione" of the recap (layout-dinamici §4.8, D-103): matches, LED seconds and spots of
 * the season, list total, discount and final total, each on its own line. Without discount only the
 * final total is shown. Amounts are decimal strings (C5).
 */
final readonly class RecapTotals
{
    public function __construct(
        public int $matches,
        public int $seconds,
        public int $spots,
        public string $listTotal,
        public ?string $discountLabel,
        public string $finalTotal,
    ) {}

    public function hasDiscount(): bool
    {
        return $this->discountLabel !== null;
    }
}
