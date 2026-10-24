<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

/**
 * Confirmation of the mandatory setup of the second factor at the first login (D-136, M01-CA1):
 * Fortify's confirmation, which checks the code and sets two_factor_confirmed_at, plus the audit
 * event in the same transaction (D-127).
 *
 * Audit: user.two_factor_configured, the user as author, without values.
 */
final class ConfirmTwoFactorSetup
{
    public function __construct(
        private readonly ConfirmTwoFactorAuthentication $confirm,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @throws ValidationException wrong code
     */
    public function handle(User $user, string $code): User
    {
        return DB::transaction(function () use ($user, $code): User {
            ($this->confirm)($user, $code);

            $this->audit->record(AuditAction::UserTwoFactorConfigured, $user, AuditSubject::user($user));

            return $user;
        });
    }
}
