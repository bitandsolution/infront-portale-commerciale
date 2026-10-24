<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Data\UserAuditValues;
use App\Domain\Users\Data\UserDetailsData;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Domain\Users\Services\AccessLinks;
use App\Domain\Users\Services\ActiveAdminGuard;
use App\Domain\Users\Services\SendAccessLink;
use App\Enums\AccessLinkType;
use App\Enums\AuditAction;
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
 * Audit (D-127): user.role_changed when the role changes, otherwise user.updated, with only the
 * changed fields before and after (read here, on the locked row); no event when nothing changes.
 */
final class UpdateUser
{
    public function __construct(
        private readonly ActiveAdminGuard $adminGuard,
        private readonly AccessLinks $links,
        private readonly SendAccessLink $sendAccessLink,
        private readonly AuditLog $audit,
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
            $before = UserAuditValues::of($locked);

            $locked->fill([
                'first_name' => trim($data->firstName),
                'last_name' => trim($data->lastName),
                'email' => $data->email,
                'phone' => CreateUser::phone($data->phone),
                'role' => $data->role,
            ])->save();

            [$old, $new] = AuditLog::changes($before, UserAuditValues::of($locked));

            if ($new !== []) {
                $this->audit->record(
                    array_key_exists('role', $new) ? AuditAction::UserRoleChanged : AuditAction::UserUpdated,
                    $actor,
                    AuditSubject::user($locked),
                    $old,
                    $new,
                );
            }

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
