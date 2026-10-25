<?php

namespace App\Http\Requests\Sports;

use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

/**
 * New alias of a club typed by the admin (F-11). Uniqueness and conflicts with the names of the
 * other clubs are checked by the Action (AddClubAlias).
 */
class ClubAliasRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $club = $this->route('club');

        return $club instanceof Club && ($this->user()?->can('update', $club) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:150'],
        ];
    }
}
