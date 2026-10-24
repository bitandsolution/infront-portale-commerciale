<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The admin reactivates a deactivated user (M01 §5). Password and second factor are kept
 * (D-281): the user signs in as before.
 *
 * ⚠️ Audit: user.reactivated from PR-05 (D-127), with $actor as author.
 */
final class ReactivateUser
{
    public function handle(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user): User {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->is_active) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivated_by' => null,
            ])->save();

            return $locked;
        });
    }
}
