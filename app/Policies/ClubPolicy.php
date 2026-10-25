<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Club;
use App\Models\User;

/**
 * Clubs, their logos and aliases (M02 F-05, F-11): admin only (D-137). The manager adds aliases
 * only while resolving the names of a calendar import (D-321, F1-04). Clubs are never deleted
 * (C4, RB-08): there is no delete permission.
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function update(User $user, Club $club): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }
}
