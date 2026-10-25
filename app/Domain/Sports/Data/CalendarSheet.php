<?php

namespace App\Domain\Sports\Data;

/**
 * Cells of the first sheet of a calendar file, as read by CalendarFileReader: the header row (the
 * first row that is not empty) and the rows below it with their line number in Excel.
 * A cell is a string, a number, a boolean, a date (DateTimeInterface), a time (DateInterval) or null.
 */
final readonly class CalendarSheet
{
    /**
     * @param  list<string>  $headers
     * @param  list<array{line: int, cells: list<mixed>}>  $rows
     */
    public function __construct(
        public int $headerLine,
        public array $headers,
        public array $rows,
    ) {}
}
