<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Season;
use App\Models\User;

/**
 * Seasons (M02 F-03): managed only by the admin (D-137, M01 §4). Status rules of a closed season (D-317) are checked by the Actions, which answer with a toast.
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class SeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function update(User $user, Season $season): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }
}
