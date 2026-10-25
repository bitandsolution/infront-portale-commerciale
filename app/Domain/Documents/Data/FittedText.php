<?php

namespace App\Domain\Documents\Data;

/**
 * A text fitted into its box (layout-dinamici §6.3): possibly cut with "…", with the font size in
 * CSS pixels to use.
 */
final readonly class FittedText
{
    public function __construct(
        public string $text,
        public float $size,
    ) {}
}
