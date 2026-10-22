<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * Users (M01 §5; D-139): only the admin creates, edits and deactivates users.
 * Everyone can see his own profile (the page arrives with PR-04, D-267).
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::UsersManage->allows($user->role);
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model) || Ability::UsersManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::UsersManage->allows($user->role);
    }

    public function update(User $user, User $model): bool
    {
        return Ability::UsersManage->allows($user->role);
    }

    /**
     * The "at least one active admin" check (RB-04) runs in the deactivation Action (PR-04), in a transaction.
     */
    public function deactivate(User $user, User $model): bool
    {
        return Ability::UsersManage->allows($user->role) && ! $user->is($model);
    }
}
