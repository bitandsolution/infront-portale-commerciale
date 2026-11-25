<?php

namespace App\Domain\Content\Data;

/**
 * One page of a slide in the document: the recap is repeated for every variant ("Proposta A / B",
 * D-396), the LED simulation for every pair of club and format with the LED in the group (D-398,
 * D-405, D-423); the others have one page without variant nor club. A page of the LED simulation
 * without configuration (of the club or generic) is not drawable: the PDF skips it (D-427) and the
 * page count leaves it out (D-499).
 */
final readonly class SlideInstance
{
    public function __construct(
        public ?string $variantLetter = null,
        public ?int $clubId = null,
        public ?string $formatCode = null,
        public bool $drawable = true,
    ) {}
}
