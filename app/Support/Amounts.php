<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Amounts in euro (C5): exact decimals with brick/math (D-336), never floats.
 *
 * - Input from the forms is in Italian notation: comma for the decimals, optional dots grouping the
 *   thousands ("3.157,89", "3157,89", "60.000"). A dot alone is never a decimal separator, so
 *   "3.157" is three thousand one hundred fifty-seven and "3157.89" is refused.
 * - Values travel as decimal strings with two decimals ("3157.89"), the form MariaDB DECIMAL(12,2)
 *   and the "decimal:2" cast use.
 */
final class Amounts
{
    /** Largest value of a DECIMAL(12,2) column. */
    public const MAX = '9999999999.99';

    private const ITALIAN = '/^(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/';

    /**
     * Italian notation → decimal string with two decimals, or null when the text is not an amount
     * (negative, more than two decimals, wrong grouping, letters). Spaces and "€" are ignored.
     */
    public static function parseItalian(string $input): ?string
    {
        $text = str_replace([' ', "\u{00A0}", '€'], '', trim($input));

        if ($text === '' || preg_match(self::ITALIAN, $text) !== 1) {
            return null;
        }

        return self::normalize(str_replace(['.', ','], ['', '.'], $text));
    }

    /**
     * Decimal string → the same amount with exactly two decimals ("60000" → "60000.00").
     * The rounding of D-336 (nearest cent, 0.5 up) applies when there are more decimals.
     */
    public static function normalize(string $decimal): string
    {
        return (string) BigDecimal::of($decimal)->toScale(2, RoundingMode::HalfUp);
    }

    public static function exceedsMax(string $decimal): bool
    {
        return BigDecimal::of($decimal)->compareTo(self::MAX) > 0;
    }

    /**
     * Decimal string → Italian notation with two decimals and grouped thousands ("3.157,89"),
     * without the euro sign: the pages add it where it is needed.
     */
    public static function italian(string $decimal): string
    {
        [$integer, $fraction] = explode('.', self::normalize($decimal));
        $negative = str_starts_with($integer, '-');
        $digits = ltrim($integer, '-');
        $grouped = strrev(implode('.', str_split(strrev($digits), 3)));

        return ($negative ? '-' : '').$grouped.','.$fraction;
    }
}
