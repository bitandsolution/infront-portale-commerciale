<?php

namespace App\Domain\Documents\Data;

/**
 * Data of the network recap (layout-dinamici §4.8): rows of the clubs and of their hospitality,
 * totals and the optional note on the relegated teams.
 */
final readonly class NetworkRecap
{
    /**
     * @param  list<RecapRow>  $rows
     */
    public function __construct(
        public array $rows,
        public RecapTotals $totals,
        public ?string $relegationNote = null,
    ) {}
}
