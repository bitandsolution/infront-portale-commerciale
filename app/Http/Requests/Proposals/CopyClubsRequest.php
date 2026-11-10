<?php

namespace App\Http\Requests\Proposals;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Copia su altri club" (M06 §5, M06-CA7; F4-05): the club whose configuration is copied, the clubs
 * that receive it and the lock version read by the page (D-216). Which clubs belong to the draft and
 * what can be copied is checked by CopyClubConfiguration.
 */
class CopyClubsRequest extends FormRequest
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
            'lock_version' => ['required', 'integer', 'min:0'],
            'source_club_id' => ['required', 'integer'],
            'target_club_ids' => ['required', 'array', 'min:1'],
            'target_club_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ProposalAttributes::names();
    }

    /**
     * @return list<int>
     */
    public function targetClubIds(): array
    {
        $ids = $this->input('target_club_ids');

        return array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($ids) ? $ids : []));
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
