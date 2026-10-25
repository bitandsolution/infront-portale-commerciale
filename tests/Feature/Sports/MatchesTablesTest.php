<?php

use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Enrollments, matchdays and matches (D-185, D-186; C3, C9; F1-03): rows written directly in SQL,
| as a SQL client would, so the constraints hold for every writer.
*/

it('C9: the database refuses a match of a club against itself, unknown statuses and stages', function () {
    $club = Club::factory()->create();
    $matchday = Matchday::query()->create(['competition_season_id' => CompetitionSeason::factory()->create()->id, 'number' => 1]);
    $row = ['matchday_id' => $matchday->id, 'home_club_id' => $club->id, 'away_club_id' => Club::factory()->create()->id, 'match_date' => '2051-09-14', 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::table('matches')->insert([...$row, 'away_club_id' => $club->id]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('matches')->insert([...$row, 'status' => 'postponed']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('matches')->insert([...$row, 'source' => 'api']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('matchdays')->insert(['competition_season_id' => $matchday->competition_season_id, 'stage' => 'final', 'number' => 2]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('matchdays')->insert(['competition_season_id' => $matchday->competition_season_id, 'stage' => 'regular', 'number' => 0]))->toThrow(QueryException::class);
});

it('F-10, M02 §8: the natural key matchday + home + away is unique', function () {
    $match = FixtureMatch::factory()->create();

    expect(fn () => FixtureMatch::factory()->create([
        'matchday_id' => $match->matchday_id, 'home_club_id' => $match->home_club_id, 'away_club_id' => $match->away_club_id,
    ]))->toThrow(QueryException::class);
});

it('M02-CA7, RB-08: a club with enrollments or matches cannot be deleted', function () {
    $enrolled = Club::factory()->create();
    ClubEnrollment::query()->create(['competition_season_id' => CompetitionSeason::factory()->create()->id, 'club_id' => $enrolled->id]);
    $match = FixtureMatch::factory()->create();

    expect(fn () => $enrolled->delete())->toThrow(QueryException::class)
        ->and(fn () => $match->homeClub->delete())->toThrow(QueryException::class)
        ->and(fn () => $match->awayClub->delete())->toThrow(QueryException::class);
});
