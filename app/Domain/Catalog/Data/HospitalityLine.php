<?php

namespace App\Domain\Catalog\Data;

use App\Models\HospitalityVariant;

/**
 * Hospitality seats of a variant in a club quote (M03 RB-05, D-055): price = seats × price per
 * seat of the published list, exact, euro VAT excluded.
 */
final readonly class HospitalityLine
{
    public function __construct(
        public HospitalityVariant $variant,
        public int $seats,
        public string $seatPrice,
        public string $price,
    ) {}
}
