<?php

namespace App\Http\Requests\Proposals;

use App\Http\Requests\Clients\ClientRequest;
use App\Models\Proposal;

/**
 * New client created from step 1 of the configurator (M05 F-02, FL-02): the same fields and rules as
 * the client form (ClientRequest), the same duplicate check in CreateClient. Only the agent makes
 * proposals: on a new proposal he must be allowed to create one, on a draft to change it; with a
 * draft the page also sends its lock version (D-216).
 */
class ProposalClientRequest extends ClientRequest
{
    public function authorize(): bool
    {
        $proposal = $this->proposal();

        return $proposal !== null
            ? $this->user()?->can('update', $proposal) ?? false
            : $this->user()?->can('create', Proposal::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->proposal() !== null) {
            $rules['lock_version'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function proposal(): ?Proposal
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal ? $proposal : null;
    }
}
