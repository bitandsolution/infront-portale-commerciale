<?php

namespace App\Domain\Documents\Data;

/**
 * The blocks of a slide composed with blocks placed in the content area (CustomBlocksLayout; D-431):
 * the elements to draw (lines, bullets, table cells, images, with absolute positions in CSS pixels),
 * whether something was cut because it did not fit even at 70%, and the reduction applied (1 = none).
 */
final readonly class CustomArrangement
{
    /**
     * @param  list<array<string, mixed>>  $elements
     */
    public function __construct(
        public array $elements,
        public bool $overflow,
        public float $scale,
    ) {}

    public function isReduced(): bool
    {
        return $this->scale < 1.0;
    }
}
