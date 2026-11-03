<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * LED durations (C6): whole seconds in the code and in the database, minutes in the interface.
 *
 * - Shown as m′ss″ ("28′30″"), with the minus sign for a negative residual ("−0′30″",
 *   M04 RB-10); in the PDF documents DocumentNumbers writes them with ' and " (Interstate has no
 *   ′ and ″, layout-dinamici §2.1).
 * - Typed as minutes with an optional decimal part, comma or dot ("28,5", "28.5", "28"); the
 *   result must be whole seconds. The step of 30″ is checked by the caller, from the settings.
 */
final class LedDuration
{
    public static function format(int $seconds): string
    {
        $sign = $seconds < 0 ? '−' : '';
        $seconds = abs($seconds);

        return sprintf('%s%d′%02d″', $sign, intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Minutes typed by the user → seconds, or null when the text is not a non-negative number of
     * minutes with whole seconds.
     */
    public static function parseMinutes(string $input): ?int
    {
        $text = str_replace(',', '.', trim($input));

        if (preg_match('/^\d{1,4}(?:\.\d{1,4})?$/', $text) !== 1) {
            return null;
        }

        try {
            return BigDecimal::of($text)->multipliedBy(60)->toScale(0)->toInt();
        } catch (MathException) {
            return null;
        }
    }

    /**
     * Seconds → minutes for the input field ("28,5", "30"), without useless decimals.
     */
    public static function minutesForInput(int $seconds): string
    {
        $minutes = (string) BigDecimal::of($seconds)->dividedBy(60, 4)->strippedOfTrailingZeros();

        return str_replace('.', ',', $minutes);
    }
}
