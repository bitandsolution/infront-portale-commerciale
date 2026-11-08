<?php

namespace App\Domain\Proposals\Data;

/**
 * One club of a draft as the configurator sends it (M06 §5; D-052, D-055, D-088, D-147; F4-03):
 * the package (a partnership tier, the free package "Libera" or none yet), at most one LED format
 * with its seconds per match (0 without a format), and the hospitality seats by variant. The order of
 * the clubs in the draft is the order of the list. Territory and visibility type are not chosen while
 * a single value is active (D-085).
 */
final readonly class DraftClubData
{
    /**
     * @param  array<int, int>  $seats  seats by hospitality variant id, only the variants with seats
     */
    public function __construct(
        public int $clubId,
        public ?int $tierId = null,
        public ?int $ledFormatId = null,
        public int $ledSeconds = 0,
        public array $seats = [],
    ) {}

    public function totalSeats(): int
    {
        return array_sum($this->seats);
    }
}
