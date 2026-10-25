<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Data\FittedText;
use App\Enums\DocumentFontWeight;

/**
 * Long texts in the layouts (club names, formats; layout-dinamici §6.3): the font size is reduced
 * down to 70% to fit the box, then the text is cut with "…". Never wrapped outside the box.
 *
 * The width is measured on the server with the same font file Chromium uses (GD + FreeType), since
 * the pages have JavaScript disabled (D-312). A 3% margin covers the small differences between the
 * two rasterisers.
 */
final class TextFitter
{
    public const MIN_RATIO = 0.7;

    private const SAFETY = 0.97;

    private const ELLIPSIS = '…';

    public function __construct(private readonly DocumentFonts $fonts) {}

    public function fit(string $text, DocumentFontWeight $weight, float $size, float $maxWidth): FittedText
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $available = $maxWidth * self::SAFETY;
        $width = $this->width($text, $weight, $size);

        if ($width <= $available) {
            return new FittedText($text, $size);
        }

        $shrunk = floor($size * $available / $width * 10) / 10;

        if ($shrunk >= $size * self::MIN_RATIO) {
            return new FittedText($text, $shrunk);
        }

        $minimum = round($size * self::MIN_RATIO, 1);
        $characters = mb_str_split($text);

        while ($characters !== []) {
            array_pop($characters);
            $candidate = rtrim(implode('', $characters)).self::ELLIPSIS;

            if ($this->width($candidate, $weight, $minimum) <= $available) {
                return new FittedText($candidate, $minimum);
            }
        }

        return new FittedText(self::ELLIPSIS, $minimum);
    }

    /**
     * Width in CSS pixels of a text at a size in CSS pixels. GD measures in points at 96 dpi, so a
     * size of N pixels is N × 0.75 points.
     */
    public function width(string $text, DocumentFontWeight $weight, float $size): float
    {
        if ($text === '') {
            return 0.0;
        }

        $box = imagettfbbox($size * 0.75, 0, $this->fonts->files()[$weight->value], $text);

        return $box === false ? 0.0 : (float) (max($box[2], $box[4]) - min($box[0], $box[6]));
    }
}
