<?php

use App\Models\Competition;
use App\Models\Season;
use Database\Factories\DisciplineFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Structure of the sports registry (D-185, progettazione-tecnica §5.3.2; C3, C9; F1-01): rows
| inserted directly in SQL, as a SQL client would, so the constraints hold for every writer.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function sportsRawSeason(array $overrides = []): int
{
    return DB::table('seasons')->insertGetId(array_merge([
        'discipline_id' => DisciplineFactory::football()->id,
        'code' => '2080-81',
        'name' => 'Stagione 2080-81',
        'starts_on' => '2080-07-01',
        'ends_on' => '2081-06-30',
        'status' => 'planned',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function sportsRawCompetition(array $overrides = []): int
{
    return DB::table('competitions')->insertGetId(array_merge([
        'discipline_id' => DisciplineFactory::football()->id,
        'code' => 'LEGA_PROVA',
        'name' => 'Lega di prova',
        'short_name' => 'Lega',
        'type' => 'league',
        'tier' => 3,
        'has_top_match_kpi' => false,
        'is_active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('D-185: creates disciplines, seasons, competitions and editions with restrictive foreign keys', function () {
    expect(Schema::getColumnListing('competition_seasons'))->toEqualCanonicalizing([
        'id', 'competition_id', 'season_id', 'standard_home_matches', 'calendar_loaded_at', 'created_at', 'updated_at',
    ]);

    foreach (['seasons', 'competitions', 'competition_seasons'] as $table) {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            expect(strtolower((string) $foreignKey['on_delete']))->toBe('restrict', "{$table}.{$foreignKey['columns'][0]}");
        }
    }

    expect(collect(Schema::getIndexes('competition_seasons'))->firstWhere('columns', ['competition_id', 'season_id'])['unique'] ?? false)->toBeTrue()
        ->and(collect(Schema::getIndexes('seasons'))->firstWhere('columns', ['discipline_id', 'code'])['unique'] ?? false)->toBeTrue()
        ->and(collect(Schema::getIndexes('competitions'))->firstWhere('columns', ['code'])['unique'] ?? false)->toBeTrue();
});

it('C9: the database refuses invalid seasons', function (array $row) {
    expect(fn () => sportsRawSeason($row))->toThrow(QueryException::class);
})->with([
    'code without consecutive format' => [['code' => '2080/81']],
    'end before start' => [['ends_on' => '2080-06-30']],
    'end equal to start' => [['ends_on' => '2080-07-01']],
    'unknown status' => [['status' => 'archived']],
]);

it('C9: the database refuses invalid competitions and editions', function () {
    expect(fn () => sportsRawCompetition(['code' => 'lega_minuscola']))->toThrow(QueryException::class)
        ->and(fn () => sportsRawCompetition(['code' => 'LEGA_TIPO', 'type' => 'friendly']))->toThrow(QueryException::class)
        ->and(fn () => sportsRawCompetition(['code' => 'LEGA_ZERO', 'tier' => 0]))->toThrow(QueryException::class);

    $season = sportsRawSeason();
    $competition = sportsRawCompetition();

    expect(fn () => DB::table('competition_seasons')->insert([
        'competition_id' => $competition, 'season_id' => $season, 'standard_home_matches' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('C3: a season with an edition cannot be deleted', function () {
    $season = Season::factory()->create();
    $season->competitionSeasons()->create(['competition_id' => Competition::factory()->create()->id, 'standard_home_matches' => 19]);

    expect(fn () => $season->delete())->toThrow(QueryException::class);
});
