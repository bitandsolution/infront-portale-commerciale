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
     * @param  bool  $managed  the resource is managed for the club (capacity row with the flag on): only
     *                         then a shortfall can be forced (F5-03, D-459)
     * @param  int  $previous  F5-04 (D-465): what the frozen version of an approved proposal being changed
     *                         commits on the resource; $remaining is then without those commitments
     */
    public function __construct(
        public Club $club,
        public InventoryResource $resource,
        public int $valueId,
        public string $name,
        public int $requested,
        public int $remaining,
        public array $letters,
        public bool $managed = true,
        public int $previous = 0,
    ) {}

    /**
     * Within the residual; F5-04 (D-465): a quantity not increased over the previous version is always
     * covered, an increase within the residual plus what the previous version committed.
     */
    public function isCovered(): bool
    {
        return $this->requested <= $this->remaining || $this->requested <= $this->previous;
    }

    /** F5-04 (D-465): beyond the residual only because the residual fell under a quantity kept as it was. */
    public function isKept(): bool
    {
        return $this->requested > $this->remaining && $this->requested <= $this->previous;
    }

    /** F5-03 (D-459): a quantity beyond the residual of a managed resource, which the admin may force. */
    public function isForceable(): bool
    {
        return $this->managed && ! $this->isCovered();
    }

    /** F5-03: the residual after the approval, negative when forced (M04 §10). */
    public function remainingAfter(): int
    {
        return $this->remaining - $this->requested;
    }

    /** "club id|resource|value id": the commitments of the approval on this resource. */
    public function key(): string
    {
        return $this->club->id.'|'.$this->resource->value.'|'.$this->valueId;
    }
}
