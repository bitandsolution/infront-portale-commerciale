<?php

namespace App\Domain\Inventory\Data;

use App\Enums\InventoryResource;

/**
 * Capacity of one resource as sent by the admin: flag "managed" and quantity (seconds per match,
 * contracts or seats).
 */
final readonly class CapacityInput
{
    public function __construct(
        public InventoryResource $resource,
        public int $valueId,
        public bool $isEnabled,
        public int $quantity,
    ) {}
}
