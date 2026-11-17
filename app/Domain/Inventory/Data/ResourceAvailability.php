<?php

namespace App\Domain\Inventory\Data;

use App\Enums\InventoryResource;

/**
 * Availability of one resource of a club in a season (M04 F-06, RB-01, RB-08): capacity with the
 * flag "managed", committed by the open commitments, residual = capacity − committed (negative after
 * a forced approval or a capacity reduced under the committed, RB-10). Not configured = no capacity
 * row yet (treated as not managed). Quantities in the unit of the resource: seconds per match,
 * contracts or seats.
 */
final readonly class ResourceAvailability
{
    public function __construct(
        public InventoryResource $resource,
        public int $valueId,
        public string $code,
        public string $name,
        public bool $isConfigured,
        public bool $isEnabled,
        public int $capacity,
        public int $committed,
        public bool $hasForced = false,
    ) {}

    public function remaining(): int
    {
        return $this->capacity - $this->committed;
    }

    /**
     * F5-03 (M04 §10, D-462): residual negative with forced commitments among the open ones: "Oltre la
     * capacità", distinguished from a capacity reduced under the committed.
     */
    public function isOverCapacity(): bool
    {
        return $this->hasForced && $this->committed > $this->capacity;
    }

    public function isBelowCommitted(): bool
    {
        return $this->committed > $this->capacity;
    }
}
