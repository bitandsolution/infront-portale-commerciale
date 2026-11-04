<?php

namespace App\Domain\Catalog\Data;

/**
 * Indicative seasonal price of a LED cell of the price list (mockup "Listino LED"; D-340; F2-06):
 * 1′ per match for the home league matches of the whole season of the club, from its calendar or
 * from the standard number (D-034). Shown under the grid, never stored.
 */
final readonly class SeasonalLedPrice
{
    public function __construct(
        public string $price,
        public int $matches,
        public bool $fromCalendar,
    ) {}
}
