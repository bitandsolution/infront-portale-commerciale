<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Proposals\Actions\SaveDocumentSlides;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\User;

/**
 * Lock of a group before a change of its document (slides composed with blocks, DM-07): the group row
 * locked like sending, approval and rejection do, then the same rule as the choice of the slides
 * (SaveDocumentSlides::editableBy, D-397): the agent on drafts and rejected groups, admin and manager
 * while the group waits. Called inside the transaction of the Action.
 */
final class GroupDocumentLock
{
    /**
     * @return array{ProposalGroup, Proposal} the locked group and its first variant (audit subject)
     */
    public function lock(ProposalGroup $group, User $actor): array
    {
        $locked = ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
        $variants = Proposal::query()->where('proposal_group_id', $locked->id)->orderBy('variant_letter')->get();

        if (! SaveDocumentSlides::editableBy($actor, $locked, $variants->all())) {
            throw ProposalsActionNotAllowed::documentNotEditable();
        }

        /** @var Proposal $first */
        $first = $variants->first();

        return [$locked, $first];
    }
}
