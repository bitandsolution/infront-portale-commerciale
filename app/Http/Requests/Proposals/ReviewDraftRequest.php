<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;

/**
 * Automatic save of a variant waiting for approval in review mode (F5-02, D-445): the fields of
 * SaveDraftRequest, for admin and manager (ProposalPolicy::review). Lock of the work, client and season
 * fixed are checked by SaveDraft::review().
 */
class ReviewDraftRequest extends SaveDraftRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal && ($this->user()?->can('review', $proposal) ?? false);
    }
}
