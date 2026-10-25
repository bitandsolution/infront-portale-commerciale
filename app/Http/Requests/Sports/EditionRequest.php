<?php

namespace App\Http\Requests\Sports;

use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Competition added to a season, or new standard number of home matches of an edition (D-034,
 * M02 §8: greater than zero). Authorization: CompetitionSeasonPolicy, checked by the controller.
 */
class EditionRequest extends FormRequest
{
    use HasSportsAttributes;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'standard_home_matches' => ['bail', 'required', 'integer', 'between:1,99'],
        ];

        if ($this->routeIs('sports.editions.store')) {
            $rules['competition_id'] = ['required', 'integer', Rule::exists(Competition::class, 'id')];
        }

        return $rules;
    }
}
