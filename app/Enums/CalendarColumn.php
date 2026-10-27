<?php

namespace App\Enums;

use App\Support\Sports\NameNormalizer;

/**
 * Columns of the calendar import layout (M02 §6, D-017). Each field is recognized from the header
 * of the file by its name or a common variant, compared in normalized form (D-325). Season and
 * competition are optional: they are chosen at upload, and when present they must match.
 * The value is the key of calendar_imports.column_mapping.
 */
enum CalendarColumn: string
{
    case Season = 'season';
    case Competition = 'competition';
    case Matchday = 'matchday';
    case Date = 'date';
    case Time = 'time';
    case Home = 'home';
    case Away = 'away';
    case Stadium = 'stadium';

    public function isRequired(): bool
    {
        return in_array($this, [self::Matchday, self::Date, self::Home, self::Away], true);
    }

    /**
     * Header names accepted for the column, in normalized form (NameNormalizer).
     *
     * @return list<string>
     */
    public function headers(): array
    {
        return match ($this) {
            self::Season => ['STAGIONE'],
            self::Competition => ['COMPETIZIONE', 'CAMPIONATO', 'GIRONE'],
            self::Matchday => ['GIORNATA', 'GG', 'TURNO', 'N GIORNATA', 'NUMERO GIORNATA'],
            self::Date => ['DATA', 'DATA PARTITA', 'GIORNO'],
            self::Time => ['ORA', 'ORARIO', 'ORA INIZIO', 'ORARIO INIZIO', 'CALCIO D INIZIO'],
            self::Home => ['SQUADRA CASA', 'SQUADRA DI CASA', 'CASA', 'SQUADRA IN CASA', 'HOME'],
            self::Away => ['SQUADRA OSPITE', 'OSPITE', 'SQUADRA IN TRASFERTA', 'TRASFERTA', 'AWAY'],
            self::Stadium => ['STADIO', 'IMPIANTO', 'CAMPO'],
        };
    }

    /**
     * Column of each field recognized in the header row (the first one wins).
     *
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    public static function detect(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $index => $header) {
            $normalized = NameNormalizer::normalize($header);

            foreach (self::cases() as $column) {
                if (! isset($mapping[$column->value]) && in_array($normalized, $column->headers(), true)) {
                    $mapping[$column->value] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Required fields without a column in the mapping.
     *
     * @param  array<string, int>  $mapping
     * @return list<self>
     */
    public static function missing(array $mapping): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $column): bool => $column->isRequired() && ! isset($mapping[$column->value]),
        ));
    }

    /**
     * Italian label (lang/it/sports.php).
     */
    public function label(): string
    {
        $label = __('sports.import_columns.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
