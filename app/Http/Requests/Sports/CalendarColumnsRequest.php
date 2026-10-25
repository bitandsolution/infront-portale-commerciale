<?php

namespace App\Http\Requests\Sports;

use App\Enums\CalendarColumn;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\CalendarImport;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Columns of the file chosen by hand for the fields of the layout (D-325): for each field the index
 * of a column, or empty. The rules on required fields and repeated columns are in MapCalendarColumns.
 */
class CalendarColumnsRequest extends FormRequest
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
        $rules = ['columns' => ['required', 'array']];

        foreach (CalendarColumn::cases() as $column) {
            $rules['columns.'.$column->value] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * @return array<string, int|null>
     */
    public function columns(): array
    {
        $columns = [];

        foreach (CalendarColumn::cases() as $column) {
            $value = $this->input('columns.'.$column->value);
            $columns[$column->value] = $value === null || $value === '' ? null : (int) $value;
        }

        return $columns;
    }
}
