<?php

namespace App\Enums;

use App\Models\HospitalityPrice;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;

/**
 * The three prices of a price list (M03 F-05, F-07, F-09; D-190), each a grid club × value of the
 * catalogue with an amount per cell:
 *
 * - led: cost for 1′ per match of a LED format (unit_price, D-054);
 * - partnership: seasonal fixed price of a tier (season_price, D-029), never for the free package;
 * - hospitality: price per seat of a variant for the season (seat_price, D-055).
 *
 * The value is the URL segment of the page and of the route that changes a cell.
 */
enum PriceKind: string
{
    case Led = 'led';
    case Partnership = 'partnership';
    case Hospitality = 'hospitality';

    /**
     * @return class-string<LedPrice|PartnershipPrice|HospitalityPrice>
     */
    public function priceModel(): string
    {
        return match ($this) {
            self::Led => LedPrice::class,
            self::Partnership => PartnershipPrice::class,
            self::Hospitality => HospitalityPrice::class,
        };
    }

    /**
     * @return class-string<LedFormat|PartnershipTier|HospitalityVariant>
     */
    public function columnModel(): string
    {
        return match ($this) {
            self::Led => LedFormat::class,
            self::Partnership => PartnershipTier::class,
            self::Hospitality => HospitalityVariant::class,
        };
    }

    /** Foreign key of the column in the price table. */
    public function columnKey(): string
    {
        return match ($this) {
            self::Led => 'led_format_id',
            self::Partnership => 'partnership_tier_id',
            self::Hospitality => 'hospitality_variant_id',
        };
    }

    /** Amount column of the price table. */
    public function amountColumn(): string
    {
        return match ($this) {
            self::Led => 'unit_price',
            self::Partnership => 'season_price',
            self::Hospitality => 'seat_price',
        };
    }

    public function auditAction(): AuditAction
    {
        return match ($this) {
            self::Led => AuditAction::LedPriceUpdated,
            self::Partnership => AuditAction::PartnershipPriceUpdated,
            self::Hospitality => AuditAction::HospitalityPriceUpdated,
        };
    }

    /** Page of the side menu (PortalPages). */
    public function pageKey(): string
    {
        return 'catalog_'.$this->value;
    }
}
