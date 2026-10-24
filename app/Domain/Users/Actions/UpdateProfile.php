<?php

namespace App\Domain\Users\Actions;

use App\Models\User;

/**
 * Personal profile (M01 §5, D-267): every user changes his own name, surname and phone.
 * E-mail and role are changed only by the admin (D-139).
 *
 * ⚠️ Audit: user.profile_updated from PR-05 (D-127).
 */
final class UpdateProfile
{
    public function handle(User $user, string $firstName, string $lastName, ?string $phone): User
    {
        $user->fill([
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'phone' => CreateUser::phone($phone),
        ])->save();

        return $user;
    }
}
