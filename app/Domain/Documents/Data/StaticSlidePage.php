<?php

namespace App\Domain\Documents\Data;

/**
 * Static slide: one 1920×1080 image on the whole page (D-112, D-116). PNG images are converted to
 * JPEG quality 85 by the pipeline (D-310).
 */
final readonly class StaticSlidePage implements DocumentPage
{
    public function __construct(public string $imagePath) {}
}
