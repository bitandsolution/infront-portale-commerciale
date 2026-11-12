<?php

namespace App\Http\Requests\Proposals;

use App\Models\ProposalGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejection of a group (D-099, D-412): the reason is optional, at most 1000 characters.
 */
class RejectGroupRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => 'motivazione'];
    }

    public function reason(): ?string
    {
        $reason = $this->string('reason')->trim()->toString();

        return $reason === '' ? null : $reason;
    }
}
