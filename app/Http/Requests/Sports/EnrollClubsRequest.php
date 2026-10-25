<?php

namespace App\Http\Requests\Sports;

use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Clubs to enroll in an edition (M02 F-06): at least one existing club. The rules on more rows (one
 * league per season, already enrolled) are in EnrollClubs. Authorization: ClubEnrollmentPolicy.
 */
class EnrollClubsRequest extends FormRequest
{
    use HasSportsAttributes;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'club_ids' => ['required', 'array', 'min:1'],
            'club_ids.*' => ['integer', 'distinct', Rule::exists(Club::class, 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function clubIds(): array
    {
        $ids = $this->input('club_ids');

        return is_array($ids) ? array_values(array_map(fn (mixed $id): int => (int) $id, $ids)) : [];
    }
}
