<?php

namespace App\Domain\Content\Data;

/**
 * A variant of a group (D-396) for the composition: its letter and its club rows, in their order.
 */
final readonly class VariantFacts
{
    /**
     * @param  list<ClubFacts>  $clubs
     */
    public function __construct(
        public string $letter,
        public array $clubs,
    ) {}
}
