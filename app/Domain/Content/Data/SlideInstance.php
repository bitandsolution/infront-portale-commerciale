<?php

namespace App\Domain\Content\Data;

/**
 * One page of a slide in the document: the recap is repeated for every variant ("Proposta A / B",
 * D-396), the LED simulation for every club with the LED (D-398, D-405); the others have one page
 * without variant nor club.
 */
final readonly class SlideInstance
{
    public function __construct(
        public ?string $variantLetter = null,
        public ?int $clubId = null,
    ) {}
}
