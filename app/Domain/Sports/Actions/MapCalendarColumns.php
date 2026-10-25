<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\CalendarColumn;
use App\Models\CalendarImport;

/**
 * Columns of the file chosen by hand in the preview, when the header does not name every required
 * field (M02 §9; D-325). Every required field needs a column, and a column serves one field only.
 * The mapping is saved with the import and the preview is rebuilt.
 */
final class MapCalendarColumns
{
    public function __construct(private readonly RefreshCalendarPreview $preview) {}

    /**
     * @param  array<string, int|null>  $columns  field => index of the column, null = none
     */
    public function handle(CalendarImport $import, array $columns): CalendarImport
    {
        $headers = $import->previewData()->headers;
        $mapping = [];

        foreach (CalendarColumn::cases() as $column) {
            $index = $columns[$column->value] ?? null;

            if ($index === null) {
                continue;
            }

            if ($index < 0 || $index >= count($headers) || in_array($index, $mapping, true)) {
                throw SportsActionNotAllowed::importColumnsInvalid();
            }

            $mapping[$column->value] = $index;
        }

        if (CalendarColumn::missing($mapping) !== []) {
            throw SportsActionNotAllowed::importColumnsInvalid();
        }

        return $this->preview->handle($import, $mapping);
    }
}
