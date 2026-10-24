<?php

namespace App\Listeners;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Auth\LoginLockedOut;
use App\Enums\AccessReason;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Access events of the audit (D-128, M10-CA2, M01-CA2; D-283), each with the IP address.
 *
 * - Login: the login is complete (password and, when configured, second factor). Fortify signs the
 *   user in only after the second factor, so a login is recorded once.
 * - Failed: wrong password, unknown e-mail or user not activated. The attempted e-mail is recorded,
 *   the password never (it travels in the credentials of the event). The login page answers the
 *   same in every case (D-214).
 * - Lockout: Laravel's event, dispatched by Fortify at every attempt made during a lock: a failed
 *   login with reason "locked". The start of the lock is LoginLockedOut.
 * - Logout: only the "Esci" button. The sessions closed by the portal (inactivity, deactivation,
 *   second factor reset) are recorded by their middleware with the reason.
 *
 * Deactivated users are recorded by AuthenticateUser, which answers them before Fortify does.
 */
class RecordAccessAudit
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->access(AuditAction::LoginSucceeded, $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = self::email($event->credentials['email'] ?? null);
        $user = $event->user instanceof User ? $event->user : self::userFor($email);

        $reason = match (true) {
            $user === null => AccessReason::UnknownEmail,
            $user->activated_at === null => AccessReason::NotActivated,
            default => AccessReason::InvalidCredentials,
        };

        $this->recordFailure($user, $email, $reason);
    }

    public function handleLockout(Lockout $event): void
    {
        $email = self::email($event->request->input('email'));

        $this->recordFailure(self::userFor($email), $email, AccessReason::Locked);
    }

    public function handleLoginLockedOut(LoginLockedOut $event): void
    {
        $email = self::email($event->email);
        $user = self::userFor($email);

        $this->audit->access(AuditAction::LockedOut, $user, $user === null ? AuditSubject::attemptedEmail($email) : null);
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->access(AuditAction::TwoFactorFailed, $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User && request()->routeIs('logout')) {
            $this->audit->access(AuditAction::Logout, $event->user);
        }
    }

    private function recordFailure(?User $user, string $email, AccessReason $reason): void
    {
        $this->audit->access(
            AuditAction::LoginFailed,
            $user,
            $user === null ? AuditSubject::attemptedEmail($email) : null,
            $reason->value,
        );
    }

    private static function email(mixed $value): string
    {
        return is_string($value) ? mb_strtolower(trim($value)) : '';
    }

    private static function userFor(string $email): ?User
    {
        return $email === '' ? null : User::query()->where('email', $email)->first();
    }
}
