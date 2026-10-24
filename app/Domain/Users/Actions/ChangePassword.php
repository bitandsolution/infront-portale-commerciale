<?php

namespace App\Domain\Users\Actions;

use App\Models\User;

/**
 * Change of the password from the profile (M01 §5; D-214, D-273). The current password is checked
 * by the request (current_password rule), the new one follows Password::defaults().
 *
 * The new hash closes every other session of the user (auth.session). The session that made the
 * change stays open: it is the same User instance of the request, whose new hash auth.session
 * stores at the end of the request.
 *
 * ⚠️ Audit: user.password_changed from PR-05 (D-127).
 */
final class ChangePassword
{
    public function handle(User $user, string $password): User
    {
        $user->forceFill(['password' => $password])->save();

        return $user;
    }
}
