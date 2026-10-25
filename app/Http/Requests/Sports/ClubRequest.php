<?php

namespace App\Http\Requests\Sports;

use App\Domain\Sports\Data\ClubData;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Club;
use App\Models\Stadium;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New club or change of a club (M02 F-05): code in capital letters, digits and "_" (only on
 * creation, then stable, C3), name, short name, home stadium, colors "#RRGGBB", club Infront
 * (D-188), top match opponent (D-253), active.
 */
class ClubRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $club = $this->route('club');

        return $club instanceof Club
            ? $this->user()?->can('update', $club) ?? false
            : $this->user()?->can('create', Club::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $color = ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/'];

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['required', 'string', 'max:60'],
            'home_stadium_id' => ['nullable', 'integer', Rule::exists(Stadium::class, 'id')],
            'primary_color' => $color,
            'secondary_color' => $color,
            'is_infront' => ['required', 'boolean'],
            'is_top_match_opponent' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];

        if (! $this->route('club') instanceof Club) {
            $rules['code'] = ['required', 'string', 'max:32', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('clubs', 'code')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $color = __('sports.validation.color');

        return is_string($color) ? ['primary_color.regex' => $color, 'secondary_color.regex' => $color] : [];
    }

    public function clubData(): ClubData
    {
        $club = $this->route('club');
        $stadium = $this->input('home_stadium_id');

        return new ClubData(
            code: $club instanceof Club ? $club->code : $this->string('code')->toString(),
            name: $this->string('name')->trim()->toString(),
            shortName: $this->string('short_name')->trim()->toString(),
            homeStadiumId: $stadium === null || $stadium === '' ? null : $this->integer('home_stadium_id'),
            primaryColor: $this->color('primary_color'),
            secondaryColor: $this->color('secondary_color'),
            isInfront: $this->boolean('is_infront'),
            isTopMatchOpponent: $this->boolean('is_top_match_opponent'),
            isActive: $this->boolean('is_active'),
        );
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'primary_color', 'secondary_color'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = mb_strtoupper(trim($value));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    private function color(string $field): ?string
    {
        $value = $this->input($field);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
