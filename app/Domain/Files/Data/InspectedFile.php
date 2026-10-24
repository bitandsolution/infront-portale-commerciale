<?php

namespace App\Domain\Files\Data;

use App\Enums\FileType;

/**
 * Outcome of the checks on a file content: its real type and, for images, its size in pixels.
 */
final readonly class InspectedFile
{
    public function __construct(
        public FileType $type,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
