<?php

namespace App\Domain\Clients\Services;

/**
 * Italian VAT number (partita IVA) of a client (M05 §8; D-081, D-361): 11 digits with the official
 * check digit. No foreign VAT numbers: the Estero territory is not managed (D-030).
 *
 * As typed, the spaces and an "IT" prefix are accepted and dropped: "IT 01234567897" is stored as
 * 01234567897.
 */
final class VatNumber
{
    /**
     * The 11 digits of the typed value, or null when the field is empty. The result is not checked:
     * see isValid().
     */
    public static function clean(?string $value): ?string
    {
        $value = strtoupper((string) preg_replace('/\s+/', '', $value ?? ''));

        if (str_starts_with($value, 'IT')) {
            $value = substr($value, 2);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Check digit of the Italian VAT number: the digits in odd position are added as they are, the
     * ones in even position are doubled (minus 9 when above 9); the total must be a multiple of 10.
     */
    public static function isValid(string $digits): bool
    {
        if (preg_match('/^[0-9]{11}$/', $digits) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $value = (int) $digit;

            if ($position % 2 === 1) {
                $value *= 2;
                $value = $value > 9 ? $value - 9 : $value;
            }

            $sum += $value;
        }

        return $sum % 10 === 0;
    }
}
