<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Sports\Data\ClubMatchCount;
use App\Models\Club;
use App\Models\LedFormat;
use App\Models\PartnershipTier;

/**
 * List prices of one club of a proposal (M03 RB-01, RB-03, RB-05; D-054, D-190; F2-06), as exact
 * decimal strings with two decimals, euro VAT excluded:
 *
 * - LED: cost for 1′ per match × seconds / 60 × home league matches of the period, rounded to the
 *   cent (0.5 up, D-336) only here, on the price of the club (D-190); "0.00" without LED;
 * - partnership: the seasonal fixed price, whatever the start date (D-029); "0.00" for the free
 *   package and without a partnership;
 * - hospitality: one line per variant;
 * - total: the sum of the three, never discounted (the discount is only on the total of the
 *   proposal, D-103, in F5).
 */
final readonly class ClubQuote
{
    /**
     * @param  list<HospitalityLine>  $hospitality
     */
    public function __construct(
        public Club $club,
        public ClubMatchCount $matches,
        public ?LedFormat $ledFormat,
        public int $ledSeconds,
        public ?string $ledUnitPrice,
        public string $ledPrice,
        public ?PartnershipTier $tier,
        public string $partnershipPrice,
        public array $hospitality,
        public string $hospitalityPrice,
        public string $total,
    ) {}
}
