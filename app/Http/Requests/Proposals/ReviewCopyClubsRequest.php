<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;

/**
 * "Copia su altri club" on a variant waiting for approval in review mode (F5-02, D-457): the fields of
 * CopyClubsRequest, for admin and manager (ProposalPolicy::review).
 */
class ReviewCopyClubsRequest extends CopyClubsRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal && ($this->user()?->can('review', $proposal) ?? false);
    }
}
