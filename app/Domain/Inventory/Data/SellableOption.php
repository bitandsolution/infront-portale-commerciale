<?php

namespace App\Domain\Inventory\Data;

use App\Enums\PriceKind;
use App\Enums\SellableStatus;

/**
 * One option of a club for the configurator (M04 F-09; D-065, D-071; F2-05): a LED format, a
 * partnership tier or a hospitality variant, with its status and the residual the agent sees.
 *
 * - listPrice: the price of the published list as an exact decimal string (cost for 1′ per match,
 *   seasonal price, price per seat), null for the free package "Libera" and for a missing price;
 *   the price of a proposal is computed by PriceCalculator (F2-06), never here.
 * - remaining: residual in the unit of the resource (seconds per match, contracts, seats), never
 *   below zero (M04 §10: after a forced approval the agent sees 0); for a format, the smallest
 *   residual of its pools (D-051); null when not managed, not available, or for the free package
 *   (no counter, D-052).
 */
final readonly class SellableOption
{
    public function __construct(
        public PriceKind $kind,
        public int $valueId,
        public string $code,
        public string $name,
        public SellableStatus $status,
        public ?string $listPrice,
        public ?int $remaining,
        public bool $isFreePackage = false,
    ) {}
}
