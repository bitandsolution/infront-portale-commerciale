<?php

namespace App\Domain\Users\Data;

use App\Models\User;

/**
 * Fields of a user recorded in the audit as values before and after (M10 §4.2, D-283):
 * name, surname, e-mail, phone, role and status. Never password, hashes, tokens or the second
 * factor: for those the event alone is recorded.
 */
final class UserAuditValues
{
    /**
     * @return array{first_name: string, last_name: string, email: string, phone: string|null, role: string, is_active: bool}
     */
    public static function of(User $user): array
    {
        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
