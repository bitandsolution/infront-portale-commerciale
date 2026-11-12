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
        // "Manca la copertina … e manca il recap …": one sentence, "aggiungile" for two slides.
        $parts = array_map(
            fn (string $warning, int $index): string => $index === 0 ? $warning : mb_strtolower(mb_substr($warning, 0, 1)).mb_substr($warning, 1),
            $missing,
            array_keys($missing),
        );

        return new self(Text::get(count($parts) > 1 ? 'documents.failures.missing_slides_many' : 'documents.failures.missing_slides', [
            'missing' => implode(' e ', $parts),
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
