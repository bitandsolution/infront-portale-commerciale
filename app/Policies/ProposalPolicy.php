<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\Proposal;
use App\Models\User;

/**
 * Proposals (M06 §3, M07; M01 §4 matrix, D-069, D-076, D-091, D-374). The agent sees, creates,
 * changes, deletes and duplicates only his own proposals (RB-14): changes and deletion only while the
 * proposal is a draft (M07 RB-08; F5 adds the correction of a rejected proposal, D-099). Admin and
 * manager consult every proposal (ProposalsViewAll); their actions on the life cycle (review,
 * approval, cancellation) arrive with F5. Queries go through Proposal::visibleTo() as a second line
 * of defence (HasOwner, column agent_id). Deactivated users are already refused by Gate::before.
 */
class ProposalPolicy
{
    /** A list of proposals: "Le mie proposte" for the agent; the back office lists arrive with F5. */
    public function viewAny(User $user): bool
    {
        return Ability::ProposalsOwn->allows($user->role) || Ability::ProposalsViewAll->allows($user->role);
    }

    public function view(User $user, Proposal $proposal): bool
    {
        return Ability::ProposalsViewAll->allows($user->role) || $this->ownsAsAgent($user, $proposal);
    }

    public function create(User $user): bool
    {
        return Ability::ProposalsOwn->allows($user->role);
    }

    /** The configurator works on the agent's own drafts (D-374). */
    public function update(User $user, Proposal $proposal): bool
    {
        return $proposal->isDraft() && $this->ownsAsAgent($user, $proposal);
    }

    /** Only drafts are deleted, by their agent (M07 RB-08). */
    public function delete(User $user, Proposal $proposal): bool
    {
        return $proposal->isDraft() && $this->ownsAsAgent($user, $proposal);
    }

    /** A new draft from any proposal of the agent, also for another client (D-091, M06-CA8). */
    public function duplicate(User $user, Proposal $proposal): bool
    {
        return $this->ownsAsAgent($user, $proposal);
    }

    private function ownsAsAgent(User $user, Proposal $proposal): bool
    {
        return $user->role === Role::Agent && Ability::ProposalsOwn->allows($user->role) && $proposal->isOwnedBy($user);
    }
}
