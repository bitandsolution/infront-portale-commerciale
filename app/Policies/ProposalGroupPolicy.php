<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\ProposalGroup;
use App\Models\User;

/**
 * Groups of variants (D-396; M07 §3; D-395, D-411; DM-03a):
 * - view: admin and manager every group (ProposalsViewAll), the agent only his own (D-411);
 * - submit: the agent of the group (ProposalsOwn); the statuses are checked by SubmitGroup;
 * - review (queue "Da approvare", rejection, then approval in DM-03b): admin and manager
 *   (ProposalsReview, D-068).
 * Deactivated users are already refused by Gate::before.
 */
class ProposalGroupPolicy
{
    public function view(User $user, ProposalGroup $group): bool
    {
        return Ability::ProposalsViewAll->allows($user->role) || $this->ownsAsAgent($user, $group);
    }

    public function submit(User $user, ProposalGroup $group): bool
    {
        return $this->ownsAsAgent($user, $group);
    }

    public function review(User $user): bool
    {
        return Ability::ProposalsReview->allows($user->role);
    }

    private function ownsAsAgent(User $user, ProposalGroup $group): bool
    {
        return $user->role === Role::Agent && Ability::ProposalsOwn->allows($user->role) && $group->isOwnedBy($user);
    }
}
