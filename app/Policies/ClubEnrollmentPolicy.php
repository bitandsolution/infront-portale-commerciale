<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\ClubEnrollment;
use App\Models\User;

/**
 * Seasonal enrollments (M02 F-06): admin only (D-137, D-321: seasons, competitions and clubs are
 * the admin's). A closed season (D-317) is checked by the Actions.
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class ClubEnrollmentPolicy
{
    public function create(User $user): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }

    public function delete(User $user, ClubEnrollment $clubEnrollment): bool
    {
        return Ability::SportsStructureManage->allows($user->role);
    }
}
