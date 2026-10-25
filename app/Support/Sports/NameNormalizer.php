<?php

namespace App\Support\Sports;

use Illuminate\Support\Str;

/**
 * Normalized form of a club or stadium name, used to recognize the names written in a calendar file
 * (F-09, F-11; D-319): capital letters, no accents, punctuation and repeated spaces removed.
 * "S.S. Lazio", "ss lazio" and "SS  LAZIO" all become "SS LAZIO".
 */
final class NameNormalizer
{
    public static function normalize(string $name): string
    {
        $ascii = Str::ascii($name, 'it');
        $letters = (string) preg_replace('/[^A-Za-z0-9]+/', ' ', $ascii);

        return mb_substr(trim(mb_strtoupper($letters)), 0, 150);
    }
}
