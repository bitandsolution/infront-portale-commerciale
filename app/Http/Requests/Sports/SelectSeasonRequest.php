<?php

namespace App\Http\Requests\Sports;

use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Season;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Season chosen in the top bar (D-317): any existing season, every role.
 */
class SelectSeasonRequest extends FormRequest
{
    use HasSportsAttributes;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'season_id' => ['required', 'integer', Rule::exists(Season::class, 'id')],
        ];
    }

    public function season(): Season
    {
        return Season::query()->findOrFail($this->integer('season_id'));
    }
}
