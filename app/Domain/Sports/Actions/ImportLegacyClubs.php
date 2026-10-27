<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\LegacyClubReport;
use App\Domain\Sports\Data\LegacyClubRow;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AliasSource;
use App\Enums\AuditAction;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\Discipline;
use App\Models\ExternalReference;
use App\Support\Sports\NameNormalizer;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * One-off creation of the clubs of the old system (D-229, D-318, D-329), from the rows of
 * idoc__teams_list read by LegacyClubFile. Run by the server command sports:import-legacy-clubs.
 *
 * - Rows with the same normalized name are one club; other duplicates (e.g. "xxx__BOLOGNA__xxx")
 *   are joined by the groups of ids passed by the command. The main row of a group is the first
 *   Infront one (deleted = 0), otherwise the first by id.
 * - Code from the alias, otherwise from the name; short name = alias, otherwise the name (D-329).
 *   A repeated or invalid code stops everything before any write.
 * - deleted = 0 → Infront club; deleted = 1 → opponent, active (M11). "r,g,b" → #RRGGBB, "0,0,0"
 *   (the default of the old system) → no colour.
 * - Every name and alias of the group becomes an alias with origin "legacy", unless it is the name
 *   of the club or it already identifies another club (anomaly). Every id becomes an "idoc"
 *   reference.
 * - Top match flag only for the clubs of D-257, by code; differences with team_top are reported.
 * - A club already in the portal (by "idoc" reference or normalized name) is not changed: only the
 *   missing aliases and references are added (D-329), so the command can run again.
 *
 * All or nothing, in one transaction, with one audit event by the system (D-318).
 */
final class ImportLegacyClubs
{
    /** D-257: opponents of the top match KPI. */
    public const array TOP_MATCH_CODES = ['LAZIO', 'INTER', 'MILAN', 'JUVENTUS', 'ROMA', 'NAPOLI'];

    /** Category of the old system that does not exist (progettazione-tecnica §5.3.2). */
    private const int MISSING_CATEGORY = 3;

    public function __construct(private readonly AuditLog $audit) {}

    /**
     * @param  list<LegacyClubRow>  $rows
     * @param  list<list<int>>  $merges  groups of ids of the old system that are the same club
     */
    public function handle(array $rows, array $merges = []): LegacyClubReport
    {
        return DB::transaction(function () use ($rows, $merges): LegacyClubReport {
            $discipline = Discipline::default();
            $report = new LegacyClubReport;
            $groups = $this->groups($rows, $merges, $report);
            $plans = $this->plans($groups, $discipline->id);

            foreach ($plans as $plan) {
                $club = $plan['existing'] ?? $this->create($plan, $discipline->id, $report);

                if ($plan['existing'] !== null) {
                    $report->existing[] = $club->code.' – '.$club->name;
                }

                $this->references($club, $plan['rows'], $report);
                $this->aliases($club, $plan['rows'], $discipline->id, $report);
                $this->checkTopMatch($club, $plan['rows'], $report);
            }

            $this->checkRows($rows, $report);

            $this->audit->record(
                AuditAction::ClubLegacyImported,
                null,
                new AuditSubject('club', null, Text::get('sports.legacy.audit_label')),
                [],
                [
                    'created' => count($report->created),
                    'existing' => count($report->existing),
                    'aliases' => $report->aliases,
                    'references' => $report->references,
                    'anomalies' => count($report->anomalies),
                ],
            );

            return $report;
        });
    }

    /**
     * Rows grouped by normalized name, then joined by the groups of the command.
     *
     * @param  list<LegacyClubRow>  $rows
     * @param  list<list<int>>  $merges
     * @return list<list<LegacyClubRow>>
     */
    private function groups(array $rows, array $merges, LegacyClubReport $report): array
    {
        $byId = [];
        $parent = [];

        foreach ($rows as $row) {
            if ($row->id <= 0 || $row->name === '') {
                $report->anomalies[] = Text::get('sports.legacy.row_skipped', ['id' => (string) $row->id]);

                continue;
            }

            $byId[$row->id] = $row;
            $parent[$row->id] = $row->id;
        }

        $find = function (int $id) use (&$parent, &$find): int {
            return $parent[$id] === $id ? $id : ($parent[$id] = $find($parent[$id]));
        };
        $join = function (int $a, int $b) use (&$parent, $find): void {
            [$rootA, $rootB] = [$find($a), $find($b)];
            $parent[max($rootA, $rootB)] = min($rootA, $rootB);
        };

        $byName = [];
        foreach ($byId as $row) {
            $normalized = NameNormalizer::normalize($row->name);

            if (isset($byName[$normalized])) {
                $join($byName[$normalized], $row->id);
            } else {
                $byName[$normalized] = $row->id;
            }
        }

        foreach ($merges as $merge) {
            $known = array_values(array_filter($merge, fn (int $id): bool => isset($byId[$id])));

            foreach (array_diff($merge, $known) as $unknown) {
                throw SportsActionNotAllowed::legacyUnknownId($unknown);
            }

            foreach (array_slice($known, 1) as $id) {
                $join($known[0], $id);
            }
        }

        $groups = [];
        foreach ($byId as $id => $row) {
            $groups[$find($id)][] = $row;
        }
        ksort($groups);

        return array_values($groups);
    }

    /**
     * What each group becomes, checked before any write (D-329): an existing club, or a new club
     * with a valid code that no other club or group uses.
     *
     * @param  list<list<LegacyClubRow>>  $groups
     * @return list<array{rows: list<LegacyClubRow>, main: LegacyClubRow, code: string, existing: Club|null}>
     */
    private function plans(array $groups, int $disciplineId): array
    {
        $plans = [];
        $conflicts = [];
        $codes = [];

        foreach ($groups as $rows) {
            $main = $this->main($rows);
            $existing = $this->existing($rows, $disciplineId);
            $code = self::code($main->alias !== '' ? $main->alias : $main->name);

            if ($existing === null) {
                if (preg_match('/^[A-Z][A-Z0-9_]*$/', $code) !== 1 || strlen($code) > 32) {
                    $conflicts[] = Text::get('sports.legacy.code_invalid', ['id' => (string) $main->id, 'name' => $main->name, 'code' => $code]);
                } elseif (isset($codes[$code]) || Club::query()->where('code', $code)->exists()) {
                    $conflicts[] = Text::get('sports.legacy.code_taken', ['id' => (string) $main->id, 'name' => $main->name, 'code' => $code]);
                }

                $codes[$code] = true;
            }

            $plans[] = ['rows' => $rows, 'main' => $main, 'code' => $code, 'existing' => $existing];
        }

        if ($conflicts !== []) {
            throw SportsActionNotAllowed::legacyConflicts(implode('; ', $conflicts));
        }

        return $plans;
    }

    /**
     * @param  list<LegacyClubRow>  $rows
     */
    private function main(array $rows): LegacyClubRow
    {
        foreach ($rows as $row) {
            if (! $row->deleted) {
                return $row;
            }
        }

        return $rows[0];
    }

    /**
     * Club already in the portal: by "idoc" reference, otherwise by normalized name, short name or
     * alias of the main row.
     *
     * @param  list<LegacyClubRow>  $rows
     */
    private function existing(array $rows, int $disciplineId): ?Club
    {
        $reference = ExternalReference::query()
            ->where('platform', ExternalReference::PLATFORM_IDOC)
            ->where('entity_type', ExternalReference::ENTITY_CLUB)
            ->whereIn('external_id', array_map(fn (LegacyClubRow $row): string => (string) $row->id, $rows))
            ->first();

        if ($reference !== null) {
            return Club::query()->find($reference->entity_id);
        }

        $names = [];
        foreach ($rows as $row) {
            $names[] = NameNormalizer::normalize($row->name);
        }

        $alias = ClubAlias::query()->where('discipline_id', $disciplineId)->whereIn('alias_normalized', $names)->first();

        if ($alias !== null) {
            return $alias->club;
        }

        return Club::query()
            ->where('discipline_id', $disciplineId)
            ->get()
            ->first(fn (Club $club): bool => in_array(NameNormalizer::normalize($club->name), $names, true)
                || in_array(NameNormalizer::normalize($club->short_name), $names, true));
    }

    /**
     * @param  array{rows: list<LegacyClubRow>, main: LegacyClubRow, code: string, existing: Club|null}  $plan
     */
    private function create(array $plan, int $disciplineId, LegacyClubReport $report): Club
    {
        $main = $plan['main'];
        $infront = array_filter($plan['rows'], fn (LegacyClubRow $row): bool => ! $row->deleted) !== [];
        $color = null;

        $ordered = array_merge([$main], array_filter($plan['rows'], fn (LegacyClubRow $row): bool => $row->id !== $main->id));

        foreach ($ordered as $row) {
            $color ??= $this->color($row, $report);
        }

        $club = Club::query()->create([
            'discipline_id' => $disciplineId,
            'code' => $plan['code'],
            'name' => mb_substr($main->name, 0, 150),
            'short_name' => mb_substr($main->alias !== '' ? $main->alias : $main->name, 0, 60),
            'primary_color' => $color,
            'is_infront' => $infront,
            'is_top_match_opponent' => in_array($plan['code'], self::TOP_MATCH_CODES, true),
            'is_active' => true,
        ]);

        $report->created[] = $club->code.' – '.$club->name;

        return $club;
    }

    /**
     * "r,g,b" of the old system as #RRGGBB; "0,0,0" (its default) and empty mean no colour.
     */
    private function color(LegacyClubRow $row, LegacyClubReport $report): ?string
    {
        $value = str_replace(' ', '', $row->color);

        if ($value === '' || $value === '0,0,0') {
            return null;
        }

        if (preg_match('/^(\d{1,3}),(\d{1,3}),(\d{1,3})$/', $value, $parts) !== 1 || max((int) $parts[1], (int) $parts[2], (int) $parts[3]) > 255) {
            $report->anomalies[] = Text::get('sports.legacy.color_invalid', ['id' => (string) $row->id, 'color' => $row->color]);

            return null;
        }

        return sprintf('#%02X%02X%02X', (int) $parts[1], (int) $parts[2], (int) $parts[3]);
    }

    /**
     * @param  list<LegacyClubRow>  $rows
     */
    private function references(Club $club, array $rows, LegacyClubReport $report): void
    {
        foreach ($rows as $row) {
            $reference = ExternalReference::query()->firstOrCreate([
                'platform' => ExternalReference::PLATFORM_IDOC,
                'entity_type' => ExternalReference::ENTITY_CLUB,
                'external_id' => (string) $row->id,
            ], ['entity_id' => $club->id]);

            if ($reference->wasRecentlyCreated) {
                $report->references++;
            } elseif ($reference->entity_id !== $club->id) {
                $report->anomalies[] = Text::get('sports.legacy.reference_other_club', ['id' => (string) $row->id, 'club' => $club->code]);
            }
        }
    }

    /**
     * Names and aliases of the rows as aliases with origin "legacy" (F-11), unless they are the name
     * of the club or already identify another club.
     *
     * @param  list<LegacyClubRow>  $rows
     */
    private function aliases(Club $club, array $rows, int $disciplineId, LegacyClubReport $report): void
    {
        $own = [NameNormalizer::normalize($club->name), NameNormalizer::normalize($club->short_name)];
        $done = [];

        foreach ($rows as $row) {
            foreach ([$row->name, $row->alias] as $alias) {
                $normalized = NameNormalizer::normalize($alias);

                if ($normalized === '' || in_array($normalized, $own, true) || isset($done[$normalized])) {
                    continue;
                }

                $done[$normalized] = true;
                $taken = ClubAlias::query()->where('discipline_id', $disciplineId)->where('alias_normalized', $normalized)->first();

                if ($taken !== null) {
                    if ($taken->club_id !== $club->id) {
                        $report->anomalies[] = Text::get('sports.legacy.alias_taken', ['alias' => $alias, 'club' => $club->code, 'other' => $taken->club->code]);
                    }

                    continue;
                }

                $other = Club::query()
                    ->where('discipline_id', $disciplineId)
                    ->whereKeyNot($club->id)
                    ->get()
                    ->first(fn (Club $candidate): bool => NameNormalizer::normalize($candidate->name) === $normalized
                        || NameNormalizer::normalize($candidate->short_name) === $normalized);

                if ($other !== null) {
                    $report->anomalies[] = Text::get('sports.legacy.alias_taken', ['alias' => $alias, 'club' => $club->code, 'other' => $other->code]);

                    continue;
                }

                ClubAlias::query()->create([
                    'club_id' => $club->id,
                    'discipline_id' => $disciplineId,
                    'alias' => mb_substr($alias, 0, 150),
                    'source' => AliasSource::Legacy,
                ]);
                $report->aliases++;
            }
        }
    }

    /**
     * D-257, D-329: the flag follows the list, the old column is only compared.
     *
     * @param  list<LegacyClubRow>  $rows
     */
    private function checkTopMatch(Club $club, array $rows, LegacyClubReport $report): void
    {
        $oldTop = array_filter($rows, fn (LegacyClubRow $row): bool => $row->top) !== [];
        $newTop = in_array($club->code, self::TOP_MATCH_CODES, true);

        if ($oldTop !== $newTop) {
            $report->anomalies[] = Text::get($newTop ? 'sports.legacy.top_added' : 'sports.legacy.top_dropped', ['club' => $club->code]);
        }
    }

    /**
     * @param  list<LegacyClubRow>  $rows
     */
    private function checkRows(array $rows, LegacyClubReport $report): void
    {
        foreach ($rows as $row) {
            if ($row->category === self::MISSING_CATEGORY) {
                $report->anomalies[] = Text::get('sports.legacy.category_missing', ['id' => (string) $row->id, 'name' => $row->name]);
            }
        }

        foreach (self::TOP_MATCH_CODES as $code) {
            if (! Club::query()->where('code', $code)->exists()) {
                $report->anomalies[] = Text::get('sports.legacy.top_missing', ['club' => $code]);
            }
        }
    }

    /**
     * D-329: capital letters without accents, spaces → "_".
     */
    public static function code(string $name): string
    {
        return str_replace(' ', '_', NameNormalizer::normalize($name));
    }
}
