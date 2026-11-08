<?php

namespace App\Domain\Proposals\Data;

use App\Models\Club;

/**
 * The price of one club of a reopened draft: the one saved on its row and the one calculated now on
 * the current list and matches (M06 RB-12, M03-CA6; F4-04). listChanged: a unit price of the list
 * (cost for 1′, partnership, seat) is different; matchesChanged: the home matches of a club with the
 * LED are different (calendar loaded or changed).
 */
final readonly class ClubPriceChange
{
    public function __construct(
        public Club $club,
        public string $saved,
        public string $current,
        public bool $listChanged,
        public bool $matchesChanged,
    ) {}
}
