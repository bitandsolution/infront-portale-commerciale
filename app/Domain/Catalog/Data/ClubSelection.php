<?php

namespace App\Domain\Catalog\Data;

use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;

/**
 * What a proposal asks of one club (M06 §4, D-088, D-147; F2-06): at most one LED format with its
 * seconds per match (both or neither, D-088), a partnership tier (the free package "Libera" has no
 * price, D-052; none = no partnership), and the hospitality seats by variant (D-055: always
 * selectable, also without a partnership). The configurator (F4) builds it from the form; the
 * residuals are its own check, not of the prices.
 */
final readonly class ClubSelection
{
    /**
     * @param  list<array{variant: HospitalityVariant, seats: int}>  $seats
     */
    public function __construct(
        public Club $club,
        public ?LedFormat $ledFormat = null,
        public int $ledSeconds = 0,
        public ?PartnershipTier $tier = null,
        public array $seats = [],
    ) {}
}
