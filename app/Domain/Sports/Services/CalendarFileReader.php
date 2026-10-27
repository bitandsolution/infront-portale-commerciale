<?php

namespace App\Domain\Sports\Services;

use App\Domain\Sports\Data\CalendarSheet;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Models\StoredFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Reads the cells of the first sheet of a calendar file (F-09) with OpenSpout (D-316): streaming,
 * no formula evaluated, no macro (the container was already checked by FileInspector, D-218).
 * Dates come back as dates, times as intervals, the other cells as text or numbers.
 *
 * The header is the first row that is not empty; empty rows below it are skipped but counted, so
 * every row keeps its line number in Excel (shown in the preview).
 */
final class CalendarFileReader
{
    /** D-322: about 380–2,000 matches per file; a larger sheet is not a calendar. */
    public const int MAX_ROWS = 3000;

    public function read(StoredFile $file): CalendarSheet
    {
        $options = new Options;
        $options->SHOULD_FORMAT_DATES = false;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new Reader($options);
        $headerLine = 0;
        $headers = [];
        $rows = [];

        try {
            $reader->open(Storage::disk(StoredFile::DISK)->path($file->path));

            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = $row->toArray();

                    if (self::isEmpty($cells)) {
                        continue;
                    }

                    if ($headerLine === 0) {
                        $headerLine = $line;
                        $headers = array_map(fn (mixed $cell): string => is_scalar($cell) ? trim((string) $cell) : '', $cells);

                        continue;
                    }

                    if (count($rows) >= self::MAX_ROWS) {
                        throw SportsActionNotAllowed::calendarTooManyRows(self::MAX_ROWS);
                    }

                    $rows[] = ['line' => $line, 'cells' => $cells];
                }

                break; // only the first sheet
            }
        } catch (SportsActionNotAllowed $exception) {
            throw $exception;
        } catch (Throwable) {
            throw SportsActionNotAllowed::calendarUnreadable();
        } finally {
            $reader->close();
        }

        if ($headerLine === 0 || $rows === []) {
            throw SportsActionNotAllowed::calendarEmpty();
        }

        return new CalendarSheet($headerLine, $headers, $rows);
    }

    /**
     * @param  list<mixed>  $cells
     */
    private static function isEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && ! (is_string($cell) && trim($cell) === '')) {
                return false;
            }
        }

        return true;
    }
}
