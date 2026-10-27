<?php

namespace App\Domain\Sports\Data;

use App\Support\Sports\NameNormalizer;

/**
 * Preview of a calendar import (FL-02 step 2), stored as JSON in calendar_imports.preview and
 * rebuilt from the file after every correction (D-325).
 *
 * Outcome of a row: "new" (match to insert), "update" (existing match whose date, time or stadium
 * change), "unchanged" (existing match, nothing changes) or "error" (row discarded). A row with an
 * unknown team is an error marked "unknown" until the name is associated with a club.
 *
 * @phpstan-type PreviewRow array{line: int, matchday: int|null, date: string|null, time: string|null, home: string, away: string, stadium: string, home_club_id: int|null, away_club_id: int|null, stadium_id: int|null, match_id: int|null, outcome: string, unknown: bool, errors: list<string>, warnings: list<string>, changes: list<string>}
 * @phpstan-type UnknownName array{name: string, rows: int}
 */
final readonly class CalendarPreview
{
    public const string NEW = 'new';

    public const string UPDATE = 'update';

    public const string UNCHANGED = 'unchanged';

    public const string ERROR = 'error';

    /**
     * @param  list<string>  $headers  header row of the file
     * @param  array<string, int>  $mapping  field => index of the column
     * @param  list<string>  $missingColumns  required fields without a column
     * @param  list<PreviewRow>  $rows
     * @param  list<UnknownName>  $unknownNames
     * @param  list<string>  $missingMatches  matches of the edition that are not in the file (D-319)
     */
    public function __construct(
        public array $headers,
        public array $mapping,
        public array $missingColumns,
        public array $rows,
        public array $unknownNames,
        public array $missingMatches,
    ) {}

    /**
     * Rows that the confirmation writes or leaves as they are (not discarded).
     */
    public function validRows(): int
    {
        return count(array_filter($this->rows, fn (array $row): bool => $row['outcome'] !== self::ERROR));
    }

    /**
     * @return array{new: int, update: int, unchanged: int, error: int, warning: int}
     */
    public function counts(): array
    {
        $counts = ['new' => 0, 'update' => 0, 'unchanged' => 0, 'error' => 0, 'warning' => 0];

        foreach ($this->rows as $row) {
            match ($row['outcome']) {
                self::NEW => $counts['new']++,
                self::UPDATE => $counts['update']++,
                self::UNCHANGED => $counts['unchanged']++,
                default => $counts['error']++,
            };

            if ($row['warnings'] !== []) {
                $counts['warning']++;
            }
        }

        return $counts;
    }

    public function isUnknownName(string $normalized): bool
    {
        foreach ($this->unknownNames as $unknown) {
            if (NameNormalizer::normalize($unknown['name']) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'headers' => $this->headers,
            'mapping' => $this->mapping,
            'missing_columns' => $this->missingColumns,
            'rows' => $this->rows,
            'unknown_names' => $this->unknownNames,
            'missing_matches' => $this->missingMatches,
        ];
    }

    /**
     * @param  array<mixed>  $data  as written by toArray()
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $headers */
        $headers = $data['headers'] ?? [];
        /** @var array<string, int> $mapping */
        $mapping = $data['mapping'] ?? [];
        /** @var list<string> $missingColumns */
        $missingColumns = $data['missing_columns'] ?? [];
        /** @var list<PreviewRow> $rows */
        $rows = $data['rows'] ?? [];
        /** @var list<UnknownName> $unknownNames */
        $unknownNames = $data['unknown_names'] ?? [];
        /** @var list<string> $missingMatches */
        $missingMatches = $data['missing_matches'] ?? [];

        return new self($headers, $mapping, $missingColumns, $rows, $unknownNames, $missingMatches);
    }
}
