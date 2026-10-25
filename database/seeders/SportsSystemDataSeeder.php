<?php

namespace Database\Seeders;

use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\Discipline;
use Illuminate\Database\Seeder;

/**
 * System data of the sports registry (progettazione-tecnica §5.6.1 A1; D-012, D-016, D-321):
 * the discipline football and the competitions of the initial perimeter, Serie A, Serie B and the
 * three groups of Serie C (Lega Pro). Called by SystemDataSeeder.
 *
 * Idempotent like SystemDataSeeder (D-295): rows are found by code and only the missing ones are
 * created; a competition renamed or deactivated by the admin is never changed back. Seasons, clubs
 * and editions are not system data: the admin enters them (D-231).
 */
class SportsSystemDataSeeder extends Seeder
{
    /**
     * code, name, short name, tier, top match KPI (D-036: only Serie A), sort order.
     *
     * @var list<array{string, string, string, int, bool, int}>
     */
    public const COMPETITIONS = [
        ['SERIE_A', 'Serie A', 'Serie A', 1, true, 10],
        ['SERIE_B', 'Serie B', 'Serie B', 2, false, 20],
        ['SERIE_C_GA', 'Serie C – Girone A', 'Serie C A', 3, false, 30],
        ['SERIE_C_GB', 'Serie C – Girone B', 'Serie C B', 3, false, 40],
        ['SERIE_C_GC', 'Serie C – Girone C', 'Serie C C', 3, false, 50],
    ];

    public function run(): void
    {
        $football = Discipline::query()->firstOrCreate(
            ['code' => Discipline::FOOTBALL],
            ['name' => 'Calcio', 'is_active' => true, 'sort_order' => 10],
        );

        $created = 0;

        foreach (self::COMPETITIONS as [$code, $name, $shortName, $tier, $topMatch, $sortOrder]) {
            $competition = Competition::query()->firstOrCreate(
                ['code' => $code],
                [
                    'discipline_id' => $football->id,
                    'name' => $name,
                    'short_name' => $shortName,
                    'type' => CompetitionType::League,
                    'tier' => $tier,
                    'has_top_match_kpi' => $topMatch,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );

            if ($competition->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command?->line(sprintf(
            'Sports registry: discipline %s %s; competitions: %d created, %d already present (left unchanged).',
            Discipline::FOOTBALL,
            $football->wasRecentlyCreated ? 'created' : 'already present',
            $created,
            count(self::COMPETITIONS) - $created,
        ));
    }
}
