<?php

namespace App\Domain\Users\Actions;

use App\Models\User;

/**
 * Clears secret and recovery codes of a user from the server (D-276): command
 * users:reset-two-factor, for whoever has SSH access, also for the only admin.
 *
 * The password stays: the user signs in with it and configures the second factor again at once
 * (EnsureTwoFactorIsConfigured). His open sessions close at the next request, because the second
 * factor they were opened with no longer exists (EnsureTwoFactorIsUnchanged).
 *
 * ⚠️ Audit: user.two_factor_reset (from the server) from PR-05 (D-127).
 */
final class ClearTwoFactor
{
    public function handle(User $user): User
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user;
    }
}
