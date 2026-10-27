<?php

namespace App\Domain\Sports\Services;

use App\Domain\Sports\Data\CalendarPreview;
use App\Domain\Sports\Data\CalendarSheet;
use App\Enums\CalendarColumn;
use App\Enums\MatchdayStage;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Stadium;
use App\Support\Sports\NameNormalizer;
use App\Support\Text;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Checks the rows of a calendar file against an edition and builds the preview (M02 FL-02, §8;
 * D-319, D-325). Nothing is written: the same analysis runs again at the confirmation, inside the
 * transaction, so the preview never decides alone what gets written.
 *
 * For each row:
 * - errors (row discarded): missing or invalid matchday or date, invalid time, unknown team (to be
 *   associated with a club), team not enrolled in the edition, same team at home and away, date
 *   outside the season, season or competition of another edition, row repeated in the file;
 * - outcome: new match, change of an existing match (natural key: edition, regular stage, matchday,
 *   home and away club, F-10) or nothing to change;
 * - warnings (row kept): unknown stadium, replaced by the home stadium of the home club (D-319); a
 *   team with two matches on the same day (M02 §8); a cancelled match stays cancelled (D-325).
 *
 * All matches are of the regular stage (D-325). Matches of the edition that are not in the file are
 * listed and left as they are (D-319). An empty stadium cell means the home stadium of the home club
 * for a new match (M02 §6) and no change for an existing one (D-326).
 *
 * @phpstan-import-type PreviewRow from CalendarPreview
 */
final class CalendarAnalyzer
{
    /**
     * @param  array<string, int>  $mapping  field => index of the column
     */
    public function analyze(CompetitionSeason $edition, CalendarSheet $sheet, array $mapping): CalendarPreview
    {
        $edition->loadMissing(['season', 'competition']);
        $missingColumns = array_map(fn (CalendarColumn $column): string => $column->value, CalendarColumn::missing($mapping));

        if ($missingColumns !== []) {
            return new CalendarPreview($sheet->headers, $mapping, $missingColumns, [], [], []);
        }

        $clubs = $this->clubNames($edition->season->discipline_id);
        $clubModels = Club::query()->where('discipline_id', $edition->season->discipline_id)->get()->keyBy('id');
        $enrolled = array_flip($edition->enrollments()->pluck('club_id')->all());
        $stadiums = $this->stadiumNames();
        $existing = $this->existingMatches($edition);

        $rows = [];
        $unknown = [];
        $seen = [];

        foreach ($sheet->rows as $sheetRow) {
            $cell = fn (CalendarColumn $column): mixed => isset($mapping[$column->value]) ? ($sheetRow['cells'][$mapping[$column->value]] ?? null) : null;
            $errors = [];
            $warnings = [];

            $home = self::text($cell(CalendarColumn::Home));
            $away = self::text($cell(CalendarColumn::Away));
            $stadium = self::text($cell(CalendarColumn::Stadium));

            $season = self::text($cell(CalendarColumn::Season));
            if ($season !== '' && ! $this->isSeason($edition, $season)) {
                $errors[] = Text::get('sports.import_errors.other_season', ['season' => $season]);
            }

            $competition = self::text($cell(CalendarColumn::Competition));
            if ($competition !== '' && ! $this->isCompetition($edition, $competition)) {
                $errors[] = Text::get('sports.import_errors.other_competition', ['competition' => $competition]);
            }

            $matchday = self::matchday($cell(CalendarColumn::Matchday));
            if ($matchday === null) {
                $errors[] = Text::get('sports.import_errors.matchday');
            }

            $rawDate = $cell(CalendarColumn::Date);
            $date = self::date($rawDate);
            if ($date === null) {
                $errors[] = self::isBlank($rawDate) ? Text::get('sports.import_errors.date_missing') : Text::get('sports.import_errors.date_invalid');
            } elseif ($date->lt($edition->season->starts_on) || $date->gt($edition->season->ends_on)) {
                $errors[] = Text::get('sports.errors.date_outside_season', ['season' => $edition->season->code]);
            }

            $rawTime = $cell(CalendarColumn::Time);
            $time = self::time($rawTime);
            if ($time === null && ! self::isBlank($rawTime)) {
                $errors[] = Text::get('sports.import_errors.time_invalid');
            }

            $homeId = $this->club($home, $clubs, $unknown, $errors);
            $awayId = $this->club($away, $clubs, $unknown, $errors);
            $isUnknown = ($home !== '' && $homeId === null) || ($away !== '' && $awayId === null);

            foreach ([$homeId, $awayId] as $clubId) {
                if ($clubId !== null && ! isset($enrolled[$clubId])) {
                    $errors[] = Text::get('sports.errors.club_not_enrolled', ['club' => $clubModels[$clubId]->name ?? '', 'edition' => $edition->label()]);
                }
            }

            if ($homeId !== null && $homeId === $awayId) {
                $errors[] = Text::get('sports.import_errors.same_club');
            }

            $key = $matchday !== null && $homeId !== null && $awayId !== null ? $matchday.'|'.$homeId.'|'.$awayId : null;
            if ($key !== null && isset($seen[$key])) {
                $errors[] = Text::get('sports.import_errors.duplicate', ['line' => (string) $seen[$key]]);
            }

            $stadiumId = null;
            if ($homeId !== null) {
                $stadiumId = $clubModels[$homeId]->home_stadium_id ?? null;

                if ($stadium !== '') {
                    $known = $stadiums[NameNormalizer::normalize($stadium)] ?? null;

                    if ($known !== null) {
                        $stadiumId = $known;
                    } else {
                        $warnings[] = Text::get('sports.import_warnings.unknown_stadium', ['stadium' => $stadium]);
                    }
                }
            }

            $outcome = CalendarPreview::ERROR;
            $matchId = null;
            $changes = [];

            if ($errors === [] && $key !== null && $date !== null) {
                $seen[$key] = $sheetRow['line'];
                $match = $existing[$key] ?? null;

                if ($match === null) {
                    $outcome = CalendarPreview::NEW;
                } else {
                    $matchId = $match->id;

                    // D-326: an empty stadium cell leaves the stadium of an existing match as it is.
                    if ($stadium === '') {
                        $stadiumId = $match->stadium_id;
                    }

                    $changes = $this->changes($match, $date, $time, $stadiumId);
                    $outcome = $changes === [] ? CalendarPreview::UNCHANGED : CalendarPreview::UPDATE;

                    if ($match->status === MatchStatus::Cancelled) {
                        $warnings[] = Text::get('sports.import_warnings.cancelled');
                    }
                }
            }

            $rows[] = [
                'line' => $sheetRow['line'],
                'matchday' => $matchday,
                'date' => $date?->toDateString(),
                'time' => $time,
                'home' => $home,
                'away' => $away,
                'stadium' => $stadium,
                'home_club_id' => $homeId,
                'away_club_id' => $awayId,
                'stadium_id' => $stadiumId,
                'match_id' => $matchId,
                'outcome' => $outcome,
                'unknown' => $isUnknown,
                'errors' => array_values(array_unique($errors)),
                'warnings' => $warnings,
                'changes' => $changes,
            ];
        }

        $rows = $this->sameDayWarnings($rows, $existing, $clubModels->all());

        return new CalendarPreview(
            $sheet->headers,
            $mapping,
            [],
            $rows,
            array_values($unknown),
            $this->missingMatches($existing, $seen),
        );
    }

    /**
     * Club recognized by its name, short name or an alias (F-11), in normalized form. An unknown
     * name is collected once for the association (FL-02 step 3).
     *
     * @param  array<string, int>  $clubs
     * @param  array<string, array{name: string, rows: int}>  $unknown
     * @param  list<string>  $errors
     */
    private function club(string $name, array $clubs, array &$unknown, array &$errors): ?int
    {
        if ($name === '') {
            $errors[] = Text::get('sports.import_errors.club_missing');

            return null;
        }

        $normalized = NameNormalizer::normalize($name);
        $clubId = $clubs[$normalized] ?? null;

        if ($clubId === null) {
            $unknown[$normalized] ??= ['name' => $name, 'rows' => 0];
            $unknown[$normalized]['rows']++;
            $errors[] = Text::get('sports.import_errors.club_unknown', ['club' => $name]);
        }

        return $clubId;
    }

    /**
     * Normalized names, short names and aliases of the clubs of the discipline.
     *
     * @return array<string, int>
     */
    private function clubNames(int $disciplineId): array
    {
        $names = [];

        foreach (Club::query()->where('discipline_id', $disciplineId)->orderBy('id')->get(['id', 'name', 'short_name']) as $club) {
            $names[NameNormalizer::normalize($club->name)] ??= $club->id;
            $names[NameNormalizer::normalize($club->short_name)] ??= $club->id;
        }

        foreach (ClubAlias::query()->where('discipline_id', $disciplineId)->get(['club_id', 'alias_normalized']) as $alias) {
            $names[$alias->alias_normalized] ??= $alias->club_id;
        }

        unset($names['']);

        return $names;
    }

    /**
     * Normalized names of the active stadiums (D-319: no aliases for stadiums).
     *
     * @return array<string, int>
     */
    private function stadiumNames(): array
    {
        $names = [];

        foreach (Stadium::query()->where('is_active', true)->orderBy('id')->get(['id', 'name']) as $stadium) {
            $names[NameNormalizer::normalize($stadium->name)] ??= $stadium->id;
        }

        return $names;
    }

    /**
     * Matches of the regular stage of the edition by natural key "matchday|home|away".
     *
     * @return array<string, FixtureMatch>
     */
    private function existingMatches(CompetitionSeason $edition): array
    {
        $matches = [];

        $query = FixtureMatch::query()
            ->join('matchdays', 'matchdays.id', '=', 'matches.matchday_id')
            ->where('matchdays.competition_season_id', $edition->id)
            ->where('matchdays.stage', MatchdayStage::Regular->value)
            ->select('matches.*')
            ->with(['matchday', 'homeClub', 'awayClub'])
            ->orderBy('matchdays.number')
            ->orderBy('matches.match_date');

        foreach ($query->get() as $match) {
            $matches[$match->matchday->number.'|'.$match->home_club_id.'|'.$match->away_club_id] = $match;
        }

        return $matches;
    }

    /**
     * What the row changes in an existing match, as readable text.
     *
     * @return list<string>
     */
    private function changes(FixtureMatch $match, Carbon $date, ?string $time, ?int $stadiumId): array
    {
        $changes = [];

        if ($match->match_date->toDateString() !== $date->toDateString()) {
            $changes[] = Text::get('sports.import_changes.date', ['from' => $match->match_date->format('d/m/Y'), 'to' => $date->format('d/m/Y')]);
        }

        if ($match->kickoff() !== $time) {
            $changes[] = Text::get('sports.import_changes.time', [
                'from' => $match->kickoff() ?? Text::get('sports.import_changes.none'),
                'to' => $time ?? Text::get('sports.import_changes.none'),
            ]);
        }

        if ($match->stadium_id !== $stadiumId) {
            $changes[] = Text::get('sports.import_changes.stadium');
        }

        return $changes;
    }

    /**
     * M02 §8: a team with two matches on the same day in the competition is a warning. The matches
     * of the edition that the file does not touch count too.
     *
     * @param  list<PreviewRow>  $rows
     * @param  array<string, FixtureMatch>  $existing
     * @param  array<int, Club>  $clubs
     * @return list<PreviewRow>
     */
    private function sameDayWarnings(array $rows, array $existing, array $clubs): array
    {
        $days = [];
        $touched = [];

        foreach ($rows as $row) {
            if ($row['outcome'] !== CalendarPreview::ERROR && $row['date'] !== null) {
                foreach ([$row['home_club_id'], $row['away_club_id']] as $clubId) {
                    $days[$row['date'].'|'.$clubId] = ($days[$row['date'].'|'.$clubId] ?? 0) + 1;
                }

                if ($row['match_id'] !== null) {
                    $touched[$row['match_id']] = true;
                }
            }
        }

        foreach ($existing as $match) {
            if (! isset($touched[$match->id]) && $match->status === MatchStatus::Scheduled) {
                foreach ([$match->home_club_id, $match->away_club_id] as $clubId) {
                    $key = $match->match_date->toDateString().'|'.$clubId;
                    $days[$key] = ($days[$key] ?? 0) + 1;
                }
            }
        }

        foreach ($rows as $index => $row) {
            if ($row['outcome'] === CalendarPreview::ERROR || $row['date'] === null) {
                continue;
            }

            foreach ([$row['home_club_id'], $row['away_club_id']] as $clubId) {
                if (($days[$row['date'].'|'.$clubId] ?? 0) > 1 && $clubId !== null) {
                    $rows[$index]['warnings'][] = Text::get('sports.warnings.same_day', [
                        'club' => isset($clubs[$clubId]) ? $clubs[$clubId]->name : '',
                        'date' => Carbon::parse($row['date'])->format('d/m/Y'),
                    ]);
                }
            }
        }

        return $rows;
    }

    /**
     * D-319: matches of the edition (regular stage) that the file does not contain: left as they are.
     *
     * @param  array<string, FixtureMatch>  $existing
     * @param  array<string, int>  $seen
     * @return list<string>
     */
    private function missingMatches(array $existing, array $seen): array
    {
        $missing = [];

        foreach ($existing as $key => $match) {
            if (! isset($seen[$key])) {
                $missing[] = $match->homeClub->short_name.' – '.$match->awayClub->short_name.', '
                    .Text::get('sports.import.matchday_label', ['number' => (string) $match->matchday->number]).', '
                    .$match->match_date->format('d/m/Y');
            }
        }

        return $missing;
    }

    private function isSeason(CompetitionSeason $edition, string $value): bool
    {
        $season = $edition->season;
        $normalized = NameNormalizer::normalize($value);
        $accepted = [
            NameNormalizer::normalize($season->code),
            NameNormalizer::normalize($season->name),
            $season->starts_on->format('Y').' '.$season->ends_on->format('Y'),
        ];

        return in_array($normalized, $accepted, true);
    }

    private function isCompetition(CompetitionSeason $edition, string $value): bool
    {
        $competition = $edition->competition;

        return in_array(NameNormalizer::normalize($value), [
            NameNormalizer::normalize($competition->name),
            NameNormalizer::normalize($competition->short_name),
            NameNormalizer::normalize($competition->code),
        ], true);
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            is_string($value) => trim($value),
            is_int($value), is_float($value) => (string) $value,
            default => '',
        };
    }

    /**
     * Whole number from 1 to 99; "7", 7, 7.0 and "7ª" are accepted.
     */
    private static function matchday(mixed $value): ?int
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (is_string($value) && preg_match('/^\s*(\d{1,2})\s*[ªa°]?\s*$/u', $value, $matches) === 1) {
            $value = (int) $matches[1];
        }

        return is_int($value) && $value >= 1 && $value <= 99 ? $value : null;
    }

    /**
     * Date of the match: an Excel date (D-316: as a date or as the number of Excel) or a text
     * "dd/mm/yyyy" (also with "-" or ".", or "yyyy-mm-dd"). Impossible dates (31/02) are refused.
     */
    private static function date(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::create((int) $value->format('Y'), (int) $value->format('m'), (int) $value->format('d'));
        }

        if ((is_int($value) || is_float($value)) && $value >= 1 && $value < 2958466) {
            return Carbon::create(1899, 12, 30)?->addDays((int) floor($value));
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2}|\d{4})$/', $value, $parts) === 1) {
            [$day, $month, $year] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
            $year = $year < 100 ? 2000 + $year : $year;
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1) {
            [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? Carbon::create($year, $month, $day) : null;
    }

    /**
     * Kick-off time "HH:MM": an Excel time (interval, date or fraction of a day) or a text "20:45"
     * or "20.45". Null when the cell is empty or invalid (the caller tells them apart).
     */
    private static function time(mixed $value): ?string
    {
        if ($value instanceof DateInterval) {
            return $value->y === 0 && $value->m === 0 && $value->d === 0 ? sprintf('%02d:%02d', $value->h, $value->i) : null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if ((is_int($value) || is_float($value)) && $value >= 0 && $value < 1) {
            $minutes = (int) round($value * 1440);

            return $minutes < 1440 ? sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60) : null;
        }

        if (is_string($value) && preg_match('/^\s*(\d{1,2})[:.](\d{2})(?::\d{2})?\s*$/', $value, $parts) === 1) {
            [$hours, $minutes] = [(int) $parts[1], (int) $parts[2]];

            return $hours < 24 && $minutes < 60 ? sprintf('%02d:%02d', $hours, $minutes) : null;
        }

        return null;
    }
}
