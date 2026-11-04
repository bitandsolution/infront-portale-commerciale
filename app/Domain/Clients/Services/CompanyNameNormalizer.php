<?php

namespace App\Domain\Clients\Services;

use Illuminate\Support\Str;

/**
 * Normalized company name for the duplicate check (M05 RB-03; D-080, D-361): "Adecco S.p.A.",
 * "ADECCO SPA" and "Adecco" all give ADECCO.
 *
 * 1. capital letters without accents;
 * 2. dots removed, so "S.p.A." and "Soc.Coop." become SPA and SOCCOOP; every other character that
 *    is not a letter or a digit becomes a space;
 * 3. the legal forms of D-361 at the end of the name removed, also more than one ("SRL SB"), as long
 *    as a word is left: a company called just "SPA" stays SPA;
 * 4. spaces removed.
 */
final class CompanyNameNormalizer
{
    /** D-361: legal forms ignored at the end of the name, SB (società benefit) included. */
    private const LEGAL_FORMS = '(?:SOC ?COOP|SCARL|SCRL|SAPA|SRLS|SRL|SPA|SAS|SNC|SS|COOP|ONLUS|SB)';

    public static function normalize(string $name): string
    {
        $text = mb_strtoupper(Str::ascii($name));
        $text = str_replace('.', '', $text);
        $text = trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $text));

        while (preg_match('/^(.+?) '.self::LEGAL_FORMS.'$/', $text, $matches) === 1) {
            $text = $matches[1];
        }

        return str_replace(' ', '', $text);
    }
}
