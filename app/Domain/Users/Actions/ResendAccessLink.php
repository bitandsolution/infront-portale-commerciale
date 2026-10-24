<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Domain\Users\Services\SendAccessLink;
use App\Enums\AccessLinkType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * New access link for a user who has still to set password and second factor: never activated,
 * or second factor reset (D-277). The previous link stops working.
 *
 * The password is replaced by a random one as well, so whoever knows an old password cannot
 * configure the second factor in place of the user (D-280).
 *
 * ⚠️ Audit: user.access_link_sent from PR-05 (D-127), with $actor as author.
 */
final class ResendAccessLink
{
    public function __construct(private readonly SendAccessLink $sendAccessLink) {}

    public function handle(User $user, User $actor): User
    {
        if ($actor->is($user)) {
            throw UserActionNotAllowed::ownAccount();
        }

        return DB::transaction(function () use ($user): User {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->is_active) {
                throw UserActionNotAllowed::inactive();
            }

            if ($locked->two_factor_confirmed_at !== null) {
                throw UserActionNotAllowed::alreadyConfigured();
            }

            $locked->forceFill(['password' => Str::random(64)])->save();

            ($this->sendAccessLink)($locked, $locked->activated_at === null
                ? AccessLinkType::Activation
                : AccessLinkType::TwoFactorReset);

            return $locked;
        });
    }
}
