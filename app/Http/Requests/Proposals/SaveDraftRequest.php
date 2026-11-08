<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Automatic save of a draft (D-165, D-372): format of the fields only. Client, season, period and
 * clubs are checked by SaveDraft (ownership, closed season, M06 §8, sellable options, residuals,
 * steps of the minutes, seats), the lock version too (D-216). The clubs (F4-03) are optional: when
 * they are not sent the saved ones stay.
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
            'clubs' => ['sometimes', 'array'],
            'clubs.*' => ['array'],
            'clubs.*.club_id' => ['required', 'integer', 'distinct'],
            'clubs.*.partnership_tier_id' => ['nullable', 'integer'],
            'clubs.*.led_format_id' => ['nullable', 'integer'],
            'clubs.*.led_seconds' => ['nullable', 'integer', 'min:0', 'max:5400'],
            'clubs.*.seats' => ['nullable', 'array'],
            'clubs.*.seats.*.variant_id' => ['required', 'integer'],
            'clubs.*.seats.*.seats' => ['required', 'integer', 'min:0', 'max:99'],
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
            clubs: $this->exists('clubs') ? $this->clubs() : null,
        );
    }

    /**
     * The clubs in the order of the page; seconds only with a format, seats only above zero.
     *
     * @return list<DraftClubData>
     */
    private function clubs(): array
    {
        $clubs = [];

        foreach ((array) $this->input('clubs', []) as $club) {
            $club = (array) $club;
            $formatId = isset($club['led_format_id']) ? (int) $club['led_format_id'] : null;
            $seats = [];

            foreach ((array) ($club['seats'] ?? []) as $item) {
                $item = (array) $item;
                $variantId = (int) ($item['variant_id'] ?? 0);

                if (array_key_exists($variantId, $seats)) {
                    throw ValidationException::withMessages(['clubs' => Text::get('proposals.club_errors.unknown')]);
                }

                $seats[$variantId] = (int) ($item['seats'] ?? 0);
            }

            $clubs[] = new DraftClubData(
                clubId: (int) ($club['club_id'] ?? 0),
                tierId: isset($club['partnership_tier_id']) ? (int) $club['partnership_tier_id'] : null,
                ledFormatId: $formatId,
                ledSeconds: $formatId !== null ? (int) ($club['led_seconds'] ?? 0) : 0,
                seats: array_filter($seats, fn (int $count): bool => $count > 0),
            );
        }

        return $clubs;
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
