<?php

namespace App\Http\Requests\Proposals;

use App\Models\ProposalGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Choice of the client among the approved variants of a group (D-410): the chosen proposal.
 */
class ChooseVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', ProposalGroup::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'proposal_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['proposal_id' => 'variante'];
    }

    public function proposalId(): int
    {
        return $this->integer('proposal_id');
    }
}
