<?php

namespace App\Domain\Clients\Data;

/**
 * Data of a commodity sector from the Sectors page (M05 F-05; D-357): name, order and active. The
 * code and the free-text flag are not editable.
 */
final readonly class CommoditySectorData
{
    public function __construct(
        public string $name,
        public int $sortOrder,
        public bool $isActive,
    ) {}
}
