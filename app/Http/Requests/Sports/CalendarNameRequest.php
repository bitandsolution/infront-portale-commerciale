<?php

namespace App\Http\Requests\Sports;

use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\CalendarImport;
use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Unknown team name of the file associated with an existing club (FL-02 step 3; D-325).
 */
class CalendarNameRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $import = $this->route('calendarImport');

        return $import instanceof CalendarImport && ($this->user()?->can('update', $import) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'club_id' => ['required', 'integer', Rule::exists(Club::class, 'id')],
        ];
    }

    public function club(): Club
    {
        return Club::query()->findOrFail($this->integer('club_id'));
    }
}
