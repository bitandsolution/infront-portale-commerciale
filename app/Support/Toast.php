<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * One-shot notification flashed to the next page (ux-architettura §4.5), read by
 * HandleInertiaRequests as props.toast. Success toasts close by themselves, errors stay.
 */
final class Toast
{
    /**
     * @return array{id: string, type: string, message: string}
     */
    public static function success(string $message): array
    {
        return self::make('success', $message);
    }

    /**
     * @return array{id: string, type: string, message: string}
     */
    public static function error(string $message): array
    {
        return self::make('error', $message);
    }

    /**
     * @return array{id: string, type: string, message: string}
     */
    private static function make(string $type, string $message): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'message' => $message,
        ];
    }
}
