<?php

use App\Domain\Sports\Data\MatchCount;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Domain\Sports\Services\MatchCounter;
use App\Enums\CompetitionType;
use App\Enums\MatchdayStage;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Support\Carbon;

/*
| Match service (M02 F-12, RB-01…RB-06; M02-CA4, CA5, CA6; D-032, D-033, D-036, D-187, D-253, D-320,
| D-328). Season 2051-52: from 1 July 2051 to 30 June 2052, 366 days (2052 is a leap year).
*/

function serviceSeason(): Season
{
    return Season::factory()->forYear(2051)->open()->create();
}

/**
 * A league edition of the season with the given clubs enrolled.
 *
 * @param  list<Club>  $clubs
 */
function serviceEdition(Season $season, array $clubs, bool $calendar = true, bool $topMatchKpi = false, CompetitionType $type = CompetitionType::League): CompetitionSeason
{
    $edition = CompetitionSeason::factory()->for($season)->create([
        'competition_id' => Competition::factory()->create(['type' => $type, 'has_top_match_kpi' => $topMatchKpi])->id,
        'standard_home_matches' => 19,
        'calendar_loaded_at' => $calendar ? now() : null,
    ]);

    foreach ($clubs as $club) {
        ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);
    }

    return $edition;
}

function serviceMatch(CompetitionSeason $edition, Club $home, Club $away, string $date, int $matchday = 1, MatchdayStage $stage = MatchdayStage::Regular, MatchStatus $status = MatchStatus::Scheduled): FixtureMatch
{
    $day = Matchday::query()->firstOrCreate(['competition_season_id' => $edition->id, 'stage' => $stage, 'number' => $matchday]);

    return FixtureMatch::factory()->create([
        'matchday_id' => $day->id,
        'home_club_id' => $home->id,
        'away_club_id' => $away->id,
        'match_date' => $date,
        'status' => $status,
    ]);
}

function serviceCount(Season $season, array $clubs, string $from, ?string $to = null): MatchCount
{
    return app(MatchCounter::class)->count($season, $clubs, Carbon::parse($from), $to !== null ? Carbon::parse($to) : null);
}

it('M02-CA4: from 01/10 to the end of the season, exactly the home league matches of the club from that date', function () {
    $season = serviceSeason();
    [$club, $rival, $other] = Club::factory()->count(3)->create()->all();
    $league = serviceEdition($season, [$club, $rival, $other]);
    $cup = serviceEdition($season, [$club, $rival], type: CompetitionType::Cup);

    serviceMatch($league, $club, $rival, '2051-09-14', 1);                       // before the start
    serviceMatch($league, $club, $other, '2051-10-01', 2);                       // counts (first day)
    serviceMatch($league, $rival, $club, '2051-10-08', 3);                       // away
    serviceMatch($league, $club, $rival, '2051-11-05', 4);                       // counts
    serviceMatch($league, $club, $other, '2052-01-10', 5, status: MatchStatus::Cancelled); // cancelled
    serviceMatch($league, $club, $rival, '2052-05-30', 1, MatchdayStage::Playoff); // playoff (D-033)
    serviceMatch($cup, $club, $rival, '2051-10-20', 1);                          // cup (RB-01)

    $count = serviceCount($season, [$club], '2051-10-01');

    expect($count->totalMatches)->toBe(2)
        ->and($count->clubs[0]->fromCalendar)->toBeTrue()
        ->and($count->periodEnd->toDateString())->toBe('2052-06-30')
        ->and($count->campaignStart?->toDateString())->toBe('2051-10-01')
        ->and($count->campaignEnd?->toDateString())->toBe('2051-11-05')
        ->and($count->campaignMonths)->toBe(2)
        ->and($count->warnings)->toBe([]);
});

it('RB-04, D-032: the campaign runs from the first to the last home match of any club of the contract', function () {
    $season = serviceSeason();
    [$a, $b, $c] = Club::factory()->count(3)->create()->all();
    $league = serviceEdition($season, [$a, $b, $c]);
    serviceMatch($league, $b, $c, '2051-08-24', 1);
    serviceMatch($league, $a, $c, '2051-09-07', 2);
    serviceMatch($league, $b, $a, '2052-05-23', 38);

    $count = serviceCount($season, [$a, $b], '2051-08-01', '2052-06-30');

    expect($count->totalMatches)->toBe(3)
        ->and($count->campaignStart?->toDateString())->toBe('2051-08-24')
        ->and($count->campaignEnd?->toDateString())->toBe('2052-05-23')
        ->and($count->campaignMonths)->toBe(10);
});

it('M02-CA5, D-187, D-320: without a calendar the standard number, prorated on the days of the contract', function () {
    $season = serviceSeason();
    $club = Club::factory()->create();
    serviceEdition($season, [$club], calendar: false);

    $whole = serviceCount($season, [$club], '2051-07-01');
    expect($whole->totalMatches)->toBe(19)
        ->and($whole->clubs[0]->prorated)->toBeFalse()
        ->and($whole->warnings)->toBe([])
        ->and($whole->campaignMonths)->toBe(12);

    // Half season: 183 of 366 days → 19 × 183 / 366 = 9.5 → 10 (.5 up).
    $half = serviceCount($season, [$club], '2051-07-01', '2051-12-30');
    expect($half->totalMatches)->toBe(10)
        ->and($half->clubs[0]->prorated)->toBeTrue()
        ->and($half->warnings)->toBe(['Calendario di '.$club->name.' non ancora caricato: 10 partite stimate dal numero standard (19) in proporzione al periodo del contratto'])
        ->and($half->campaignStart?->toDateString())->toBe('2051-07-01')
        ->and($half->campaignEnd?->toDateString())->toBe('2051-12-30')
        ->and($half->campaignMonths)->toBe(6)
        ->and($half->topMatchesStatus)->toBe(MatchCount::TOP_NOT_APPLICABLE);

    // 182 days → 9.45 → 9.
    expect(serviceCount($season, [$club], '2051-07-01', '2051-12-29')->totalMatches)->toBe(9);
});

it('D-320: the period is limited to the dates of the season', function () {
    $season = serviceSeason();
    $club = Club::factory()->create();
    serviceEdition($season, [$club], calendar: false);

    $count = serviceCount($season, [$club], '2051-01-01', '2053-01-01');

    expect($count->periodStart->toDateString())->toBe('2051-07-01')
        ->and($count->periodEnd->toDateString())->toBe('2052-06-30')
        ->and($count->totalMatches)->toBe(19);

    expect(fn () => serviceCount($season, [$club], '2052-07-01'))
        ->toThrow(SportsActionNotAllowed::class, 'Il periodo del contratto è fuori dalla stagione 2051-52');
});

it('M02-CA6, RB-06, D-253: top matches only in a competition with the KPI, when the away club has the flag', function () {
    $season = serviceSeason();
    $top = Club::factory()->create(['is_top_match_opponent' => true]);
    $plain = Club::factory()->create();
    $home = Club::factory()->create();
    $serieA = serviceEdition($season, [$home, $top, $plain], topMatchKpi: true);
    serviceMatch($serieA, $home, $top, '2051-09-07', 1);
    serviceMatch($serieA, $home, $plain, '2051-09-21', 3);
    serviceMatch($serieA, $top, $home, '2051-10-05', 5); // away: not a home match of $home

    $count = serviceCount($season, [$home], '2051-07-01');
    expect($count->topMatches)->toBe(1)
        ->and($count->topMatchesStatus)->toBe(MatchCount::TOP_AVAILABLE)
        ->and($count->clubs[0]->topMatches)->toBe(1);

    $bHome = Club::factory()->create();
    $bTop = Club::factory()->create(['is_top_match_opponent' => true]);
    $serieB = serviceEdition($season, [$bHome, $bTop]);
    serviceMatch($serieB, $bHome, $bTop, '2051-09-07', 1);

    $countB = serviceCount($season, [$bHome], '2051-07-01');
    expect($countB->topMatches)->toBeNull()
        ->and($countB->topMatchesStatus)->toBe(MatchCount::TOP_NOT_APPLICABLE)
        ->and($countB->clubs[0]->topMatches)->toBeNull();
});

it('D-328: with mixed calendars each club keeps its rule; months from the contract; top matches n.d. without the Serie A calendar', function () {
    $season = serviceSeason();
    [$a, $rival] = Club::factory()->count(2)->create()->all();
    $b = Club::factory()->create();
    $serieA = serviceEdition($season, [$a, $rival], topMatchKpi: true);
    serviceEdition($season, [$b], calendar: false);
    serviceMatch($serieA, $a, $rival, '2051-09-07', 1);

    $count = serviceCount($season, [$a, $b], '2051-07-01');
    expect($count->totalMatches)->toBe(1 + 19)
        ->and($count->campaignStart?->toDateString())->toBe('2051-07-01')
        ->and($count->campaignMonths)->toBe(12)
        ->and($count->topMatchesStatus)->toBe(MatchCount::TOP_AVAILABLE)
        ->and($count->fromCalendar())->toBeFalse();

    $c = Club::factory()->create();
    serviceEdition($season, [$c], calendar: false, topMatchKpi: true);
    $missing = serviceCount($season, [$a, $c], '2051-07-01');
    expect($missing->topMatches)->toBeNull()
        ->and($missing->topMatchesStatus)->toBe(MatchCount::TOP_NOT_AVAILABLE);
});

it('D-328: a club without a league in the season stops the calculation with a clear message', function () {
    $season = serviceSeason();
    $club = Club::factory()->create(['name' => 'Società Senza Campionato']);
    serviceEdition($season, [$club], type: CompetitionType::Cup);

    expect(fn () => serviceCount($season, [$club], '2051-07-01'))
        ->toThrow(SportsActionNotAllowed::class, 'Società Senza Campionato non è iscritto a un campionato della stagione 2051-52: l\'amministratore lo iscrive dalla pagina della stagione');
});

it('a contract without home matches in the period has no campaign', function () {
    $season = serviceSeason();
    [$club, $rival] = Club::factory()->count(2)->create()->all();
    $league = serviceEdition($season, [$club, $rival]);
    serviceMatch($league, $club, $rival, '2051-09-07', 1);

    $count = serviceCount($season, [$club], '2051-10-01', '2051-10-31');

    expect($count->totalMatches)->toBe(0)
        ->and($count->campaignStart)->toBeNull()
        ->and($count->campaignMonths)->toBe(0);
});

it('sports:matches shows the matches, the campaign and the top matches without writing anything', function () {
    $season = serviceSeason();
    $home = Club::factory()->create(['code' => 'ALFA', 'name' => 'Società Alfa']);
    $top = Club::factory()->create(['code' => 'BETA', 'is_top_match_opponent' => true]);
    $serieA = serviceEdition($season, [$home, $top], topMatchKpi: true);
    serviceMatch($serieA, $home, $top, '2051-09-07', 1);
    serviceMatch($serieA, $top, $home, '2051-12-07', 2);
    serviceMatch($serieA, $home, $top, '2052-03-07', 3);
    $matches = FixtureMatch::query()->count();

    $this->artisan('sports:matches', ['season' => '2051-52', 'clubs' => ['alfa'], '--from' => '01/09/2051', '--to' => '31/03/2052'])
        ->expectsOutputToContain('Stagione 2051-52, dal 01/09/2051 al 31/03/2052')
        ->expectsOutputToContain('Totale partite: 2')
        ->expectsOutputToContain('Campagna: 7 mesi (07/09/2051 → 07/03/2052)')
        ->expectsOutputToContain('Top match: 2')
        ->assertSuccessful();

    expect(FixtureMatch::query()->count())->toBe($matches);

    $this->artisan('sports:matches', ['season' => '2051-52', 'clubs' => ['NESSUNO']])
        ->expectsOutputToContain('Club sconosciuto: NESSUNO')
        ->assertFailed();

    $this->artisan('sports:matches', ['season' => '2051-52', 'clubs' => ['ALFA'], '--from' => '31/02/2052'])
        ->expectsOutputToContain('Data non valida: 31/02/2052 (formato gg/mm/aaaa)')
        ->assertFailed();
});
