<?php

namespace App\Support;

/**
 * Support code shown to the user when a system error occurs (ux-architettura §4.5; D-265).
 *
 * One code per request, generated on first use: the same value goes into the log
 * (exception context) and into the toast or the error page, so the admin can find the error.
 * Bound as a scoped singleton in AppServiceProvider.
 */
final class SupportCode
{
    /** No 0/O and 1/I: the code is read aloud on the phone. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 6;

    private ?string $value = null;

    public function value(): string
    {
        return $this->value ??= self::generate();
    }

    public static function generate(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    public static function pattern(): string
    {
        return '/^['.self::ALPHABET.']{'.self::LENGTH.'}$/';
    }
}
