<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Proposals\Data\DraftData;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Automatic save of a draft (D-165, D-372): format of the fields only. Client, season and period are
 * checked by SaveDraft (ownership, closed season, M06 §8), the lock version too (D-216).
 */
class SaveDraftRequest extends FormRequest
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
            'client_id' => ['required', 'integer'],
            'season_id' => ['nullable', 'integer'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ProposalAttributes::names();
    }

    public function draftData(): DraftData
    {
        return new DraftData(
            clientId: $this->integer('client_id'),
            seasonId: $this->filled('season_id') ? $this->integer('season_id') : null,
            startsOn: $this->filled('starts_on') ? $this->string('starts_on')->toString() : null,
            endsOn: $this->filled('ends_on') ? $this->string('ends_on')->toString() : null,
        );
    }

    public function lockVersion(): int
    {
        return $this->integer('lock_version');
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
