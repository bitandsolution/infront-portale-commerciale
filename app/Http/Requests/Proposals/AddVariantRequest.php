<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Aggiungi variante" (D-396; DM-01): from a draft of the agent (ProposalPolicy::update); the page
 * sends the step it is on, to open the new variant at the same step.
 */
class AddVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal && ($this->user()?->can('update', $proposal) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'step' => ['nullable', 'integer', 'between:1,9'],
        ];
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
