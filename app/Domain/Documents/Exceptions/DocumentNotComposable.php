<?php

namespace App\Domain\Documents\Exceptions;

use App\Support\Text;
use RuntimeException;

/**
 * The document of a group cannot be composed with the library and the data of today (D-416): no
 * approved variant, a mandatory slide missing, a background without its image. Retrying does not
 * help: the document is marked "failed" at once with this message, the admin fixes the library and
 * regenerates it.
 */
final class DocumentNotComposable extends RuntimeException
{
    public static function noApprovedVariants(): self
    {
        return new self(Text::get('documents.failures.no_approved_variants'));
    }

    /**
     * @param  list<string>  $missing  warnings of the composition (cover, recap)
     */
    public static function missingSlides(array $missing, string $season): self
    {
        return new self(Text::get('documents.failures.missing_slides', [
            'missing' => implode('; ', $missing),
            'season' => $season,
        ]));
    }

    public static function slideWithoutImage(string $slide): self
    {
        return new self(Text::get('documents.failures.slide_without_image', ['slide' => $slide]));
    }

    public static function fileMissing(string $what): self
    {
        return new self(Text::get('documents.failures.file_missing', ['file' => $what]));
    }
}
