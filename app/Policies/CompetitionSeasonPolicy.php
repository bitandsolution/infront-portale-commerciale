<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\CompetitionSeason;
use App\Models\User;

/**
 * Editions (competition × season, D-185) and their standard number of matches: admin only (D-137, D-321). A closed season (D-317) is checked by the Actions.
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class CompetitionSeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function update(User $user, CompetitionSeason $competitionSeason): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function delete(User $user, CompetitionSeason $competitionSeason): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }
}
