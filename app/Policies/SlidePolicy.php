<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Slide;
use App\Models\User;

/**
 * Slide library (M08 §3; D-109, D-406): admin and manager. Deactivated users are already refused by
 * Gate::before (AppServiceProvider). Slides are never deleted (C4).
 */
class SlidePolicy
{
    public function viewAny(User $user): bool
    {
        return Ability::ContentManage->allows($user->role);
    }

    public function create(User $user): bool
    {
        return Ability::ContentManage->allows($user->role);
    }

    public function update(User $user, Slide $slide): bool
    {
        return Ability::ContentManage->allows($user->role);
    }
}
