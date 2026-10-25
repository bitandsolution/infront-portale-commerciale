<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\FixtureMatch;
use App\Models\User;

/**
 * Matches (M02 F-08): admin and manager (D-137, M01 §4 "calendari, import, partite, stadi").
 * Matches are cancelled and restored, never deleted (D-319). A closed season (D-317) is checked by
 * the Actions. Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class FixtureMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }

    public function update(User $user, FixtureMatch $fixtureMatch): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }
}
