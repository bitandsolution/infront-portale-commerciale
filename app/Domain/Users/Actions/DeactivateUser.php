<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Domain\Users\Services\AccessLinks;
use App\Domain\Users\Services\ActiveAdminGuard;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The admin deactivates a user (M01 §5, RB-05): the user cannot sign in any more and his open
 * sessions close at the next request (EnsureUserIsActive); his data stay. Any pending access link
 * stops working.
 *
 * Never the admin himself, never the last active admin (RB-04, M01-CA5): the check runs in the
 * transaction with the rows of the admins locked, so two concurrent deactivations cannot leave the
 * portal without an admin (ActiveAdminGuard).
 *
 * For an agent the reassignment of his clients arrives with slice F3 (M01-CA4, D-082, D-282);
 * his proposals stay in his name (D-083).
 *
 * Audit (D-127): user.deactivated, author $actor, in the same transaction.
 */
final class DeactivateUser
{
    public function __construct(
        private readonly ActiveAdminGuard $adminGuard,
        private readonly AccessLinks $links,
        private readonly AuditLog $audit,
    ) {}

    public function handle(User $user, User $actor): User
    {
        if ($actor->is($user)) {
            throw UserActionNotAllowed::ownAccount();
        }

        return DB::transaction(function () use ($user, $actor): User {
            $activeAdmins = $this->adminGuard->lockActiveAdmins();

            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->is_active) {
                return $locked;
            }

            if ($locked->role === Role::Admin) {
                $this->adminGuard->ensureNotLast($locked, $activeAdmins);
            }

            $locked->forceFill([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $actor->id,
            ])->save();

            $this->links->delete($locked);

            $this->audit->record(AuditAction::UserDeactivated, $actor, AuditSubject::user($locked), ['is_active' => true], ['is_active' => false]);

            return $locked;
        });
    }
}
