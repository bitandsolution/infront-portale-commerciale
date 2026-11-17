<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;

/**
 * Automatic save of the working copy of an approved variant (F5-04, D-466): the fields of
 * SaveDraftRequest, for the admin (ProposalPolicy::revise). Client and season fixed are checked by
 * SaveDraft::review().
 */
class RevisionDraftRequest extends SaveDraftRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal && ($this->user()?->can('revise', $proposal) ?? false);
    }
}
