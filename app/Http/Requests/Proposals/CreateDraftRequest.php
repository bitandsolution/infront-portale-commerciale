<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Step 1 of a new proposal (D-375): the client chosen among the agent's own clients. Ownership and
 * status of the client are checked by CreateDraft.
 */
class CreateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Proposal::class) ?? false;
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
