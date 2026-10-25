<?php

namespace App\Domain\Documents\Services;

use RuntimeException;

/**
 * Images of the slides in the PDF (progettazione-tecnica §5.5.2 step 3, M09 §7, D-310): PNG slides
 * become JPEG quality 85 at the same 1920×1080; JPEG images are used as they are (re-encoding would
 * only lose quality).
 *
 * In S1 the converted file lives in the folder of the job. Slice F7 keeps it as a derivative of the
 * stored file (table stored_file_derivatives, D-310), so it is converted only once.
 */
final class SlideImages
{
    public const JPEG_QUALITY = 85;

    /**
     * Writes the JPEG version of an image into the target path.
     */
    public function toJpeg(string $source, string $target): void
    {
        $size = @getimagesize($source);
        $type = is_array($size) ? $size[2] : null;

        if ($type === IMAGETYPE_JPEG) {
            if (! copy($source, $target)) {
                throw new RuntimeException("Cannot copy the image {$source}.");
            }

            return;
        }

        $image = $type === IMAGETYPE_PNG ? @imagecreatefrompng($source) : false;

        if ($image === false) {
            throw new RuntimeException("The slide image {$source} is not a readable PNG or JPEG.");
        }

        // Transparent areas become white, as on a printed page.
        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));

        if ($canvas === false) {
            throw new RuntimeException('GD cannot create the JPEG canvas.');
        }

        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        if (! imagejpeg($canvas, $target, self::JPEG_QUALITY)) {
            throw new RuntimeException("Cannot write the JPEG {$target}.");
        }
    }
}
