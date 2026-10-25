<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Services\SendAccessLink;
use App\Enums\AccessLinkType;
use App\Enums\AccessReason;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Self-service reset of the password from the login page (M01 §5; D-279; PR-08).
 *
 * Only an active, activated user with the second factor configured receives a link (48 hours,
 * once, one per user: it replaces any previous link). The link opens the same page as the reset
 * started by the admin: new password confirmed with the code of the authenticator app.
 *
 * - The current password keeps working until the new one is set: otherwise anybody could lock a
 *   colleague out by typing his address.
 * - The caller answers the same way in every case (no enumeration of the accounts): this Action
 *   returns nothing.
 * - At most 3 links per address in an hour; beyond that nothing is sent (the IP limit is on the
 *   route).
 *
 * Audit (D-285): access.password_reset_requested for every request, with the IP address and, when
 * no link was sent, the reason (unknown address, deactivated, not activated, no second factor, too
 * many requests). The completion is user.password_reset (CompletePasswordReset).
 */
final class RequestPasswordReset
{
    public const MAX_LINKS_PER_HOUR = 3;

    public function __construct(
        private readonly SendAccessLink $sendAccessLink,
        private readonly AuditLog $audit,
    ) {}

    public function handle(string $email): void
    {
        $email = mb_strtolower(trim($email));

        DB::transaction(function () use ($email): void {
            /** @var User|null $user */
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            $reason = $this->refusal($user, $email);

            $this->audit->access(
                AuditAction::PasswordResetRequested,
                $user,
                $user === null ? AuditSubject::attemptedEmail($email) : null,
                $reason?->value,
            );

            if ($user !== null && $reason === null) {
                ($this->sendAccessLink)($user, AccessLinkType::PasswordForgotten);
            }
        });
    }

    /**
     * Why no link is sent, or null when the user receives it (the request then counts in the
     * hourly limit of the address).
     */
    private function refusal(?User $user, string $email): ?AccessReason
    {
        $reason = match (true) {
            $user === null => AccessReason::UnknownEmail,
            ! $user->is_active => AccessReason::Deactivated,
            $user->activated_at === null => AccessReason::NotActivated,
            $user->two_factor_confirmed_at === null => AccessReason::TwoFactorNotConfigured,
            default => null,
        };

        if ($reason !== null) {
            return $reason;
        }

        $key = 'password-forgotten:'.$email;

        if (RateLimiter::tooManyAttempts($key, self::MAX_LINKS_PER_HOUR)) {
            return AccessReason::Throttled;
        }

        RateLimiter::hit($key, 3600);

        return null;
    }
}
