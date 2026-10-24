<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Clears secret and recovery codes of a user from the server (D-276): command
 * users:reset-two-factor, for whoever has SSH access, also for the only admin.
 *
 * The password stays: the user signs in with it and configures the second factor again at once
 * (EnsureTwoFactorIsConfigured). His open sessions close at the next request, because the second
 * factor they were opened with no longer exists (EnsureTwoFactorIsUnchanged).
 *
 * Audit (D-127, D-276): user.two_factor_reset with the system as author, in the same transaction.
 */
final class ClearTwoFactor
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $this->audit->record(AuditAction::UserTwoFactorReset, null, AuditSubject::user($user));

            return $user;
        });
    }
}
