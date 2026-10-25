<?php

namespace App\Http\Requests\Sports;

use App\Domain\Sports\Data\CompetitionData;
use App\Enums\CompetitionType;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New competition or change of a competition (M02 F-02): code in capital letters, digits and "_"
 * (only on creation, then stable, C3), name, short name, type, tier (1 = Serie A, 2 = Serie B,
 * 3 = Serie C), top match KPI (D-036), active, order in the lists.
 */
class CompetitionRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $competition = $this->route('competition');

        return $competition instanceof Competition
            ? $this->user()?->can('update', $competition) ?? false
            : $this->user()?->can('create', Competition::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:40'],
            'type' => ['required', 'string', Rule::enum(CompetitionType::class)],
            'tier' => ['bail', 'required', 'integer', 'between:1,9'],
            'has_top_match_kpi' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['bail', 'required', 'integer', 'between:0,9999'],
        ];

        if (! $this->route('competition') instanceof Competition) {
            $rules['code'] = ['required', 'string', 'max:32', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('competitions', 'code')];
        }

        return $rules;
    }

    public function competitionData(): CompetitionData
    {
        $competition = $this->route('competition');

        return new CompetitionData(
            code: $competition instanceof Competition ? $competition->code : $this->string('code')->toString(),
            name: $this->string('name')->trim()->toString(),
            shortName: $this->string('short_name')->trim()->toString(),
            type: CompetitionType::from($this->string('type')->toString()),
            tier: $this->integer('tier'),
            hasTopMatchKpi: $this->boolean('has_top_match_kpi'),
            isActive: $this->boolean('is_active'),
            sortOrder: $this->integer('sort_order'),
        );
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => mb_strtoupper(trim($code))]);
        }
    }
}
