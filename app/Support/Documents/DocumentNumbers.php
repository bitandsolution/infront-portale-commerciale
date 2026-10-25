<?php

namespace App\Support\Documents;

use InvalidArgumentException;

/**
 * Numbers as written in the documents (layout-dinamici §2.2). Amounts arrive as decimal strings
 * (C5: never float) and are formatted without converting them to a number.
 */
final class DocumentNumbers
{
    /**
     * "107.996,00 €": Italian thousands separator, two decimals, euro sign after a space.
     */
    public static function amount(string $decimal): string
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $decimal, $parts) !== 1) {
            throw new InvalidArgumentException("Not a decimal amount: {$decimal}");
        }

        $integer = self::thousands(ltrim($parts[2], '0') ?: '0');
        $cents = str_pad($parts[3] ?? '', 2, '0');

        return $parts[1].$integer.','.$cents.' €';
    }

    /**
     * "1.234": whole number with the Italian thousands separator.
     */
    public static function integer(int $value): string
    {
        return ($value < 0 ? '-' : '').self::thousands((string) abs($value));
    }

    /**
     * LED minutes per match, from whole seconds (C6): 60 → 1'00", 90 → 1'30".
     */
    public static function minutesPerMatch(int $seconds): string
    {
        return intdiv($seconds, 60)."'".str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT).'"';
    }

    /**
     * LED minutes of a total, from whole seconds: 1710 → 28' 30".
     */
    public static function totalMinutes(int $seconds): string
    {
        return self::integer(intdiv($seconds, 60))."' ".str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT).'"';
    }

    private static function thousands(string $digits): string
    {
        return ltrim(strrev(implode('.', str_split(strrev($digits), 3))), '.');
    }
}
