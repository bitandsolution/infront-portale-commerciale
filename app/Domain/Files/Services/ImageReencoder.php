<?php

namespace App\Domain\Files\Services;

use App\Domain\Files\Exceptions\InvalidFile;
use App\Enums\FileType;
use GdImage;
use LogicException;

/**
 * Re-encodes every uploaded image with GD (D-218): the stored file holds only the pixels, so any
 * foreign content hidden in the original (a PHP or HTML tail, comments, EXIF and other metadata)
 * is gone.
 *
 * - PNG: lossless, maximum compression, transparency kept.
 * - JPEG: compressed again at quality 90. EXIF and colour profiles are dropped, the EXIF
 *   orientation is not applied (logos are graphics, not photos).
 *
 * Decoding needs about 4 bytes per pixel: when the memory limit of PHP is lower than needed, it is
 * raised for the re-encoding only, up to MEMORY_CEILING, then restored.
 */
final class ImageReencoder
{
    public const JPEG_QUALITY = 90;

    private const PNG_COMPRESSION = 9;

    /** Highest memory limit the re-encoding may ask for (a 5000×5000 logo needs about 200 MB). */
    private const MEMORY_CEILING = 512 * 1024 * 1024;

    public function reencode(string $contents, FileType $type, int $width, int $height): string
    {
        if (! $type->isImage()) {
            throw new LogicException("Only images are re-encoded, not {$type->value}.");
        }

        return $this->withMemoryFor($width, $height, function () use ($contents, $type): string {
            $image = @imagecreatefromstring($contents);
            if ($image === false) {
                throw InvalidFile::unreadable();
            }

            return $this->encode($image, $type);
        });
    }

    private function encode(GdImage $image, FileType $type): string
    {
        ob_start();

        try {
            if ($type === FileType::Png) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $written = imagepng($image, null, self::PNG_COMPRESSION);
            } else {
                $written = imagejpeg($image, null, self::JPEG_QUALITY);
            }
        } finally {
            $output = (string) ob_get_clean();
        }

        if (! $written || $output === '') {
            throw InvalidFile::unreadable();
        }

        return $output;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withMemoryFor(int $width, int $height, callable $callback): mixed
    {
        $original = (string) ini_get('memory_limit');
        $limit = $original === '' ? -1 : ini_parse_quantity($original);

        if ($limit < 0) {
            return $callback();
        }

        // Decoded image, encoder buffers and a margin for the rest of the request.
        $needed = memory_get_usage() + $width * $height * 8 + 32 * 1024 * 1024;

        if ($needed <= $limit) {
            return $callback();
        }

        if ($needed > self::MEMORY_CEILING || ini_set('memory_limit', (string) $needed) === false) {
            throw InvalidFile::tooHeavyToProcess();
        }

        try {
            return $callback();
        } finally {
            ini_set('memory_limit', $original);
        }
    }
}
