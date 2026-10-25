<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Stadium;
use App\Models\User;

/**
 * Stadiums (M02 F-07): admin and manager (D-137, M01 §4 "calendari, import, partite, stadi").
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class StadiumPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }

    public function update(User $user, Stadium $stadium): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }
}
