<?php

namespace App\Enums;

/**
 * Kind of a file derived from a stored file (D-310, D-420), stored in stored_file_derivatives.kind
 * (C8). DM-05 has one kind: the JPEG thumbnail of a slide, at most 480×270 pixels, for the step
 * "Documento" on a tablet. The JPEG q85 of the PDF pipeline arrives with F7.
 */
enum FileDerivativeKind: string
{
    case Thumbnail = 'thumbnail';

    /**
     * Largest width and height of the derived image (the proportions of the original are kept).
     *
     * @return array{int, int}
     */
    public function box(): array
    {
        return match ($this) {
            self::Thumbnail => [480, 270],
        };
    }

    /**
     * Purpose of the derived file in stored_files.
     */
    public function purpose(): FilePurpose
    {
        return match ($this) {
            self::Thumbnail => FilePurpose::Thumbnail,
        };
    }
}
