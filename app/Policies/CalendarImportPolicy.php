<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\CalendarImport;
use App\Models\User;

/**
 * Calendar imports (M02 F-09, FL-02): admin and manager (D-137, M01 §4 "calendari, import, partite,
 * stadi"; M02-CA8). Open imports and closed seasons are checked by the Actions. Deactivated users
 * are already refused by Gate::before (AppServiceProvider).
 */
class CalendarImportPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }

    public function view(User $user, CalendarImport $calendarImport): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }

    public function update(User $user, CalendarImport $calendarImport): bool
    {
        return Ability::SportsCalendarsManage->allows($user->role);
    }
}
