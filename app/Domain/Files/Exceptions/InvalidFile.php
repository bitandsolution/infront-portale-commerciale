<?php

namespace App\Domain\Files\Exceptions;

use App\Support\Text;
use RuntimeException;

/**
 * A file refused by the checks on its content (D-218, C10). The message is the Italian text shown
 * to the user (lang/it/files.php); reason is the stable key, for the tests and the logs.
 */
final class InvalidFile extends RuntimeException
{
    /**
     * @param  array<string, string>  $replace
     */
    private function __construct(public readonly string $reason, array $replace = [])
    {
        parent::__construct(Text::get('files.errors.'.$reason, $replace));
    }

    public static function empty(): self
    {
        return new self('empty');
    }

    public static function tooLarge(int $bytes, int $maxBytes): self
    {
        return new self('too_large', ['size' => self::megabytes($bytes), 'max' => self::megabytes($maxBytes)]);
    }

    /**
     * @param  list<string>  $allowed  Italian names of the accepted types
     */
    public static function typeNotAllowed(array $allowed): self
    {
        return new self('type_not_allowed', ['types' => implode(Text::get('files.types_separator'), $allowed)]);
    }

    public static function unreadable(): self
    {
        return new self('unreadable');
    }

    public static function cmyk(): self
    {
        return new self('cmyk');
    }

    public static function wrongDimensions(int $width, int $height, int $requiredWidth, int $requiredHeight): self
    {
        return new self('wrong_dimensions', [
            'width' => (string) $width,
            'height' => (string) $height,
            'required_width' => (string) $requiredWidth,
            'required_height' => (string) $requiredHeight,
        ]);
    }

    public static function tooManyPixels(int $width, int $height, int $maxSide): self
    {
        return new self('too_many_pixels', ['width' => (string) $width, 'height' => (string) $height, 'max' => (string) $maxSide]);
    }

    public static function tooHeavyToProcess(): self
    {
        return new self('too_heavy_to_process');
    }

    public static function macros(): self
    {
        return new self('macros');
    }

    public static function notXlsx(): self
    {
        return new self('not_xlsx');
    }

    public static function tooLargeUnpacked(): self
    {
        return new self('too_large_unpacked');
    }

    /**
     * "2" for whole megabytes, "3,1" otherwise (Italian decimal comma).
     */
    private static function megabytes(int $bytes): string
    {
        $megabytes = $bytes / (1024 * 1024);

        return fmod($megabytes, 1.0) === 0.0
            ? number_format($megabytes, 0, ',', '.')
            : number_format($megabytes, 1, ',', '.');
    }
}
