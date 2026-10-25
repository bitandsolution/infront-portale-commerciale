<?php

use App\Enums\AliasSource;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\Stadium;
use Database\Factories\DisciplineFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Stadiums, clubs, aliases and external references (D-185, D-186; C3, C9; F1-02): rows written
| directly in SQL, as a SQL client would, so the constraints hold for every writer.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function clubsRawClub(array $overrides = []): int
{
    return DB::table('clubs')->insertGetId(array_merge([
        'discipline_id' => DisciplineFactory::football()->id,
        'code' => 'CLUB_RAW',
        'name' => 'AC Prova',
        'short_name' => 'Prova',
        'is_infront' => false,
        'is_top_match_opponent' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('C9: the database refuses invalid clubs and stadiums', function () {
    expect(fn () => clubsRawClub(['code' => 'lazio']))->toThrow(QueryException::class)
        ->and(fn () => clubsRawClub(['code' => 'CLUB_COLOR', 'primary_color' => '#c8102e']))->toThrow(QueryException::class)
        ->and(fn () => clubsRawClub(['code' => 'CLUB_COLOR2', 'secondary_color' => 'red']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('stadiums')->insert(['name' => 'Stadio', 'city' => 'Roma', 'capacity' => 0, 'is_active' => true]))->toThrow(QueryException::class);

    expect(clubsRawClub(['primary_color' => '#C8102E']))->toBeInt();
});

it('D-185: an alias never points to two clubs of the same discipline', function () {
    $lazio = Club::factory()->create();
    $roma = Club::factory()->create();
    ClubAlias::query()->create(['club_id' => $lazio->id, 'discipline_id' => $lazio->discipline_id, 'alias' => 'S.S. Lazio', 'source' => AliasSource::Manual]);

    expect(ClubAlias::query()->sole()->alias_normalized)->toBe('S S LAZIO')
        ->and(fn () => ClubAlias::query()->create(['club_id' => $roma->id, 'discipline_id' => $roma->discipline_id, 'alias' => 's s  lazio', 'source' => AliasSource::Manual]))
        ->toThrow(QueryException::class);
});

it('C9: alias source and normalized alias are checked by the database', function () {
    $club = clubsRawClub();
    $row = ['club_id' => $club, 'discipline_id' => DisciplineFactory::football()->id, 'alias' => 'X', 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::table('club_aliases')->insert([...$row, 'alias_normalized' => 'X', 'source' => 'other']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('club_aliases')->insert([...$row, 'alias_normalized' => '', 'source' => 'manual']))->toThrow(QueryException::class);
});

it('D-186: external references are unique per platform, type and external id, and more of them may point to one club', function () {
    $club = Club::factory()->create();
    $row = ['platform' => 'idoc', 'entity_type' => 'club', 'entity_id' => $club->id, 'created_at' => now(), 'updated_at' => now()];

    DB::table('external_references')->insert([...$row, 'external_id' => '21']);
    DB::table('external_references')->insert([...$row, 'external_id' => '35']);

    expect(DB::table('external_references')->where('entity_id', $club->id)->count())->toBe(2)
        ->and(fn () => DB::table('external_references')->insert([...$row, 'external_id' => '21']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('external_references')->insert([...$row, 'platform' => 'IDOC', 'external_id' => '99']))->toThrow(QueryException::class);
});

it('RB-08: a stadium used by a club cannot be deleted', function () {
    $stadium = Stadium::factory()->create();
    Club::factory()->create(['home_stadium_id' => $stadium->id]);

    expect(fn () => $stadium->delete())->toThrow(QueryException::class);
});
