<?php

namespace App\Domain\Sports\Services;

use App\Domain\Sports\Data\LegacyClubRow;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;

/**
 * Reads the CSV of the clubs of the old system (D-318), extracted by Antonio with the query of
 * docs/dev/club-vecchio-sistema.md: a header row with the columns of idoc__teams_list, separated by
 * commas or semicolons, UTF-8 (a BOM is ignored). HTML entities of the old texts are decoded
 * ("ATTIVIT&Agrave;" → "ATTIVITÀ"). No SQL is parsed and no dump is read (D-318).
 */
final class LegacyClubFile
{
    public const array COLUMNS = ['id_team', 'team_name', 'alias', 'main_color', 'team_top', 'deleted', 'team_category'];

    /**
     * @return list<LegacyClubRow>
     */
    public function read(string $path): array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw SportsActionNotAllowed::legacyFileUnreadable($path);
        }

        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $header = array_shift($lines) ?? '';
        $separator = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
        $columns = array_map(fn (?string $name): string => strtolower(trim((string) $name)), str_getcsv($header, $separator, '"', ''));
        $positions = [];

        foreach (self::COLUMNS as $column) {
            $position = array_search($column, $columns, true);

            if ($position === false) {
                throw SportsActionNotAllowed::legacyFileColumns(implode(', ', self::COLUMNS));
            }

            $positions[$column] = $position;
        }

        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line, $separator, '"', '');
            $value = fn (string $column): string => self::text($cells[$positions[$column]] ?? '');

            $rows[] = new LegacyClubRow(
                (int) $value('id_team'),
                $value('team_name'),
                $value('alias'),
                $value('main_color'),
                $value('team_top') === '1',
                $value('deleted') === '1',
                (int) $value('team_category'),
            );
        }

        return $rows;
    }

    private static function text(?string $value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
