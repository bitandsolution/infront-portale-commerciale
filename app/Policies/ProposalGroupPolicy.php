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
 *   (ProposalsReview, D-068);
 * - editDocument (slides of the document, DM-05, D-397): the agent of the group and admin and
 *   manager; the statuses (agent on drafts and rejected groups, reviewers on groups waiting for
 *   approval) are checked by SaveDocumentSlides.
 * - takeOverReview (F5-01, D-446): the admin only takes the lock of the work held by another user.
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

    /** F5-03 (M07 RB-06; D-064, D-070): only the admin forces an approval beyond the availability. */
    public function force(User $user): bool
    {
        return Ability::ProposalsForce->allows($user->role) && $this->review($user);
    }

    /** F5-05 (M07 F-07; D-095, D-447): only the admin cancels an approved group. */
    public function cancel(User $user): bool
    {
        return Ability::ProposalsManageApproved->allows($user->role);
    }

    public function takeOverReview(User $user): bool
    {
        return $user->role === Role::Admin && $this->review($user);
    }

    public function editDocument(User $user, ProposalGroup $group): bool
    {
        return $this->ownsAsAgent($user, $group) || $this->review($user);
    }

    private function ownsAsAgent(User $user, ProposalGroup $group): bool
    {
        return $user->role === Role::Agent && Ability::ProposalsOwn->allows($user->role) && $group->isOwnedBy($user);
    }
}
