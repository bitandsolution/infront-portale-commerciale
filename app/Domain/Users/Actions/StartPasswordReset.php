<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Domain\Users\Services\SendAccessLink;
use App\Enums\AccessLinkType;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reset of the password started by the admin (M01 §5; D-279).
 *
 * The current password stops working at once (random hash nobody knows), so every open session
 * of the user closes (auth.session, D-273). The user receives a link (48 hours) to choose a new
 * password, confirmed with the code of his authenticator app: the second factor stays the same.
 *
 * Only for a user who has the second factor; otherwise the admin sends a new access link
 * (ResendAccessLink). The self-service reset from the login page arrives with PR-08.
 *
 * Audit (D-127): user.password_reset_requested, author $actor, in the same transaction.
 */
final class StartPasswordReset
{
    public function __construct(
        private readonly SendAccessLink $sendAccessLink,
        private readonly AuditLog $audit,
    ) {}

    public function handle(User $user, User $actor): User
    {
        if ($actor->is($user)) {
            throw UserActionNotAllowed::ownAccount();
        }

        return DB::transaction(function () use ($user, $actor): User {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->is_active) {
                throw UserActionNotAllowed::inactive();
            }

            if ($locked->two_factor_confirmed_at === null) {
                throw UserActionNotAllowed::twoFactorNotConfigured();
            }

            $locked->forceFill(['password' => Str::random(64)])->save();

            $this->audit->record(AuditAction::UserPasswordResetRequested, $actor, AuditSubject::user($locked));

            ($this->sendAccessLink)($locked, AccessLinkType::PasswordReset);

            return $locked;
        });
    }
}
