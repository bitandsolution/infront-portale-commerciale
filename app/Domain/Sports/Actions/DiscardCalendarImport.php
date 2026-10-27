<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\CalendarImportStatus;
use App\Models\CalendarImport;
use Illuminate\Support\Facades\DB;

/**
 * An import in preview is given up (FL-02): nothing is written to the calendar, the preview is
 * cleared and the file stays in the archive (C10). No audit event: no data of the registry changes.
 */
final class DiscardCalendarImport
{
    public function handle(CalendarImport $import): CalendarImport
    {
        return DB::transaction(function () use ($import): CalendarImport {
            $import = CalendarImport::query()->lockForUpdate()->findOrFail($import->id);

            if (! $import->isOpen()) {
                throw SportsActionNotAllowed::importClosed();
            }

            $import->update(['status' => CalendarImportStatus::Discarded, 'preview' => null]);

            return $import;
        });
    }
}
