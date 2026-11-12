<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\User;

/**
 * Configuration of the simulation of the logo on the LED (D-398; DM-06): admin and manager see it
 * (Contenuti, ContentManage), only the admin changes it ("configurazione dell'admin", D-398).
 */
class LedMockupSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::ContentManage->allows($user->role);
    }

    public function update(User $user): bool
    {
        return $user->role === Role::Admin;
    }
}
