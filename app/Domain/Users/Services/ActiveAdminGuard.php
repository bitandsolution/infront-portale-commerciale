<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * "At least one active admin" (RB-04, M01 §9, M01-CA5; D-209; progettazione-tecnica §5.3.6).
 *
 * Deactivation and change of role run in a transaction that first locks the rows of the active
 * admins (SELECT … FOR UPDATE, in id order). Two concurrent operations lock the same rows, so the
 * second one waits for the first to commit and then counts the admins left: the system can never
 * be left without an active admin.
 */
final class ActiveAdminGuard
{
    /**
     * Locks the rows of the active admins and returns their ids. Call it first in the transaction,
     * before locking the user being changed, so every operation takes the locks in the same order.
     *
     * @return list<int>
     */
    public function lockActiveAdmins(): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The active admins must be locked inside a transaction.');
        }

        $ids = User::query()
            ->where('role', Role::Admin->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return array_values($ids);
    }

    /**
     * Refuses the operation when the user is the only active admin among the locked ones.
     *
     * @param  list<int>  $activeAdminIds  from lockActiveAdmins() in the same transaction
     */
    public function ensureNotLast(User $user, array $activeAdminIds): void
    {
        if (in_array($user->id, $activeAdminIds, true) && count($activeAdminIds) <= 1) {
            throw UserActionNotAllowed::lastActiveAdmin();
        }
    }
}
