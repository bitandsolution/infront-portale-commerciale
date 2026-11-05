<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\CommoditySector;
use App\Models\User;

/**
 * Commodity sectors (M05 F-05; D-075, D-357): admin only (M01 §4). Deactivated users are already
 * refused by Gate::before (AppServiceProvider).
 */
class CommoditySectorPolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::CommoditySectorsManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::CommoditySectorsManage->allows($user->role);
    }

    public function update(User $user, CommoditySector $sector): bool
    {
        return Ability::CommoditySectorsManage->allows($user->role);
    }
}
