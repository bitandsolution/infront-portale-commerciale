<?php

namespace App\Domain\Proposals\Data;

use App\Enums\InventoryResource;
use App\Models\Club;

/**
 * What the variants of a group ask together of one resource of a club (D-216, D-396; DM-03b): every
 * variant commits at the approval, so the quantities are added up and compared with the residual of
 * the resource. Quantities in the unit of the resource: seconds per match, contracts or seats.
 */
final readonly class InventoryNeed
{
    /**
     * @param  list<string>  $letters  the variants asking for the resource (empty for a single proposal)
     */
    public function __construct(
        public Club $club,
        public InventoryResource $resource,
        public int $valueId,
        public string $name,
        public int $requested,
        public int $remaining,
        public array $letters,
    ) {}

    public function isCovered(): bool
    {
        return $this->requested <= $this->remaining;
    }
}
