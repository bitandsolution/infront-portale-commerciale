<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Data\UserDetailsData;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Domain\Users\Services\AccessLinks;
use App\Domain\Users\Services\ActiveAdminGuard;
use App\Domain\Users\Services\SendAccessLink;
use App\Enums\AccessLinkType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The admin changes data and role of a user (M01 §5, D-139).
 *
 * - Change of role: never his own, and never the last active admin (RB-04, M01-CA5), checked in
 *   the transaction with the rows of the admins locked (ActiveAdminGuard).
 * - Change of e-mail: the link sent to the old address stops working; if the user has still to
 *   set password and second factor, a new link goes to the new address (D-281).
 *
 * ⚠️ Audit: user.updated / user.role_changed from PR-05 (D-127), with $actor as author and the old
 * values read here before the change.
 */
final class UpdateUser
{
    public function __construct(
        private readonly ActiveAdminGuard $adminGuard,
        private readonly AccessLinks $links,
        private readonly SendAccessLink $sendAccessLink,
    ) {}

    public function handle(User $user, UserDetailsData $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $activeAdmins = $this->adminGuard->lockActiveAdmins();

            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($data->role !== $locked->role) {
                if ($actor->is($locked)) {
                    throw UserActionNotAllowed::ownAccount();
                }

                if ($locked->role === Role::Admin) {
                    $this->adminGuard->ensureNotLast($locked, $activeAdmins);
                }
            }

            $oldEmail = $locked->email;

            $locked->fill([
                'first_name' => trim($data->firstName),
                'last_name' => trim($data->lastName),
                'email' => $data->email,
                'phone' => CreateUser::phone($data->phone),
                'role' => $data->role,
            ])->save();

            if ($locked->email !== $oldEmail) {
                $this->links->deleteFor($oldEmail);

                if ($locked->is_active && $locked->two_factor_confirmed_at === null) {
                    ($this->sendAccessLink)($locked, $locked->activated_at === null
                        ? AccessLinkType::Activation
                        : AccessLinkType::TwoFactorReset);
                }
            }

            return $locked;
        });
    }
}
