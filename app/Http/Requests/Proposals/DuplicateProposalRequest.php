<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Duplica proposta" (D-091, M06-CA8; F4-05): the client of the new draft, one of the agent's active,
 * not merged clients (checked by DuplicateProposal).
 */
class DuplicateProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal && ($this->user()?->can('duplicate', $proposal) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ProposalAttributes::names();
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
