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
 * Reset of the second factor by the admin, e.g. smartphone lost (M01 §5, D-140; D-280).
 *
 * Secret and recovery codes are cleared and the password is replaced by a random one nobody
 * knows: the new hash closes every open session of the user (auth.session, D-273), and whoever
 * knows only the old password cannot configure a second factor in his place. The user receives
 * a link (48 hours) to set password and second factor on the activation page.
 *
 * Not on his own account: the admin who lost his second factor uses the server command
 * users:reset-two-factor (D-276).
 *
 * Audit (D-127): user.two_factor_reset, author $actor, in the same transaction (no values: the
 * secret and the codes never reach the audit).
 */
final class ResetTwoFactor
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

            $locked->forceFill([
                'password' => Str::random(64),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $this->audit->record(AuditAction::UserTwoFactorReset, $actor, AuditSubject::user($locked));

            ($this->sendAccessLink)($locked, $locked->activated_at === null
                ? AccessLinkType::Activation
                : AccessLinkType::TwoFactorReset);

            return $locked;
        });
    }
}
