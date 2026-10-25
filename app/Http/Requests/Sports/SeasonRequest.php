<?php

namespace App\Http\Requests\Sports;

use App\Domain\Sports\Data\SeasonData;
use App\Enums\SeasonStatus;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Season;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * New season or change of a season (M02 F-03, §8): code "AAAA-AA" with consecutive years (only on
 * creation, then stable), name, start and end dates (end after start), status (D-317).
 * The overlap with other seasons is checked by the Action (a rule on more rows, C9).
 */
class SeasonRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $season = $this->route('season');

        return $season instanceof Season
            ? $this->user()?->can('update', $season) ?? false
            : $this->user()?->can('create', Season::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'status' => ['required', 'string', Rule::enum(SeasonStatus::class)],
        ];

        if (! $this->route('season') instanceof Season) {
            $rules['code'] = [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && preg_match('/^(\d{4})-(\d{2})$/', $value, $parts) === 1
                        && ((int) $parts[1] + 1) % 100 !== (int) $parts[2]) {
                        $fail(__('sports.validation.season_code_years'));
                    }
                },
                Rule::unique('seasons', 'code'),
            ];
        }

        return $rules;
    }

    public function seasonData(): SeasonData
    {
        $season = $this->route('season');

        return new SeasonData(
            code: $season instanceof Season ? $season->code : $this->string('code')->toString(),
            name: $this->string('name')->trim()->toString(),
            startsOn: Carbon::parse($this->string('starts_on')->toString())->startOfDay(),
            endsOn: Carbon::parse($this->string('ends_on')->toString())->startOfDay(),
            status: SeasonStatus::from($this->string('status')->toString()),
        );
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => trim($code)]);
        }
    }
}
