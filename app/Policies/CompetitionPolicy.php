<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Competition;
use App\Models\User;

/**
 * Competitions (M02 F-02): managed only by the admin (D-137, M01 §4).
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class CompetitionPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function update(User $user, Competition $competition): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }
}
