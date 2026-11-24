<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * KPI and digital of the clubs (M08 F-05; D-109, D-491; F6-01): admin and manager see and change them
 * (Contenuti, ContentManage); the agent never.
 */
class ClubKpiValuePolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::ContentManage->allows($user->role);
    }

    public function update(User $user): bool
    {
        return Ability::ContentManage->allows($user->role);
    }
}
