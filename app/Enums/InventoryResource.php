<?php

namespace App\Enums;

use App\Models\HospitalityCapacity;
use App\Models\HospitalityVariant;
use App\Models\InventoryPool;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;

/**
 * Resources of the inventory (M04 §4; D-191), stored in inventory_commitments.resource_type (C8):
 *
 * - pool: LED pool, in seconds per match (a format consumes the same seconds on each of its pools,
 *   D-051);
 * - partnership: contracts of a tier (the free package "Libera" has no counter, D-052);
 * - hospitality: seats of a variant.
 */
enum InventoryResource: string
{
    case Pool = 'pool';
    case Partnership = 'partnership';
    case Hospitality = 'hospitality';

    /**
     * @return class-string<PoolCapacity|PartnershipCapacity|HospitalityCapacity>
     */
    public function capacityModel(): string
    {
        return match ($this) {
            self::Pool => PoolCapacity::class,
            self::Partnership => PartnershipCapacity::class,
            self::Hospitality => HospitalityCapacity::class,
        };
    }

    /**
     * @return class-string<InventoryPool|PartnershipTier|HospitalityVariant>
     */
    public function valueModel(): string
    {
        return match ($this) {
            self::Pool => InventoryPool::class,
            self::Partnership => PartnershipTier::class,
            self::Hospitality => HospitalityVariant::class,
        };
    }

    /** Foreign key of the value, the same in the capacity table and in inventory_commitments. */
    public function valueKey(): string
    {
        return match ($this) {
            self::Pool => 'inventory_pool_id',
            self::Partnership => 'partnership_tier_id',
            self::Hospitality => 'hospitality_variant_id',
        };
    }

    /** Quantity column of the capacity table. */
    public function quantityColumn(): string
    {
        return match ($this) {
            self::Pool => 'seconds_per_match',
            self::Partnership => 'quantity',
            self::Hospitality => 'seats',
        };
    }

    public function auditAction(): AuditAction
    {
        return match ($this) {
            self::Pool => AuditAction::PoolCapacityUpdated,
            self::Partnership => AuditAction::PartnershipCapacityUpdated,
            self::Hospitality => AuditAction::HospitalityCapacityUpdated,
        };
    }
}
