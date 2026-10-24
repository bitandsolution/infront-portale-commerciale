<?php

namespace App\Support;

/**
 * Interface text from lang/it as a string (CLAUDE.md §9). __() may return an array for a group
 * key: here a missing or non-string key returns the key itself, so it shows up during review.
 */
final class Text
{
    /**
     * @param  array<string, string>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
