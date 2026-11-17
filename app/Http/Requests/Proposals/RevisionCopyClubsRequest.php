<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;

/**
 * "Copia su altri club" on the working copy of an approved variant (F5-04, D-466): the fields of
 * CopyClubsRequest, for the admin (ProposalPolicy::revise).
 */
class RevisionCopyClubsRequest extends CopyClubsRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal && ($this->user()?->can('revise', $proposal) ?? false);
    }
}
