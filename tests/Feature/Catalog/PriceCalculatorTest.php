<?php

use App\Domain\Catalog\Data\ClubSelection;
use App\Domain\Catalog\Data\Quote;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceCalculator;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\CompetitionType;
use App\Enums\MatchdayStage;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\HospitalityPrice;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\Matchday;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\Setting;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/*
| List prices (M03 RB-01…RB-05, RB-09; M03-CA1, CA2, CA3, CA9; D-029, D-054, D-055, D-057, D-147,
| D-190, D-336; F2-06). Season 2051-52: from 1 July 2051 to 30 June 2052. Lazio in a league edition
| without a calendar (19 standard home matches) unless a test loads one.
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->season = Season::factory()->forYear(2051)->open()->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->edition = CompetitionSeason::factory()->create([
        'season_id' => $this->season->id,
        'competition_id' => Competition::factory()->create(['type' => CompetitionType::League])->id,
        'standard_home_matches' => 19,
    ]);
    $this->lazio = priceCalculatorClub($this->edition, 'SS Lazio', 'LAZIO');
});

function priceCalculatorClub(CompetitionSeason $edition, string $name, string $code): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, 'code' => $code]);
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);

    return $club;
}

function priceCalculatorFormat(string $code): LedFormat
{
    return LedFormat::query()->where('code', $code)->firstOrFail();
}

function priceCalculatorTier(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function priceCalculatorVariant(string $code): HospitalityVariant
{
    return HospitalityVariant::query()->where('code', $code)->firstOrFail();
}

function priceCalculatorLed(PriceList $priceList, Club $club, string $format, string $price): void
{
    LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $club->id, 'led_format_id' => priceCalculatorFormat($format)->id, 'unit_price' => $price]);
}

function priceCalculatorQuote(Season $season, array $selections, string $from = '2051-07-01', ?string $to = null): Quote
{
    return app(PriceCalculator::class)->quote($season, $selections, Carbon::parse($from), $to === null ? null : Carbon::parse($to));
}

/* LED (RB-01, D-054, D-190). */

it('M03-CA1: Lazio 1st row at 3.157,89 € for 1′ and 19 matches gives about 60.000 € (59.999,91 €); with 1′30″ 1,5 times', function () {
    priceCalculatorLed($this->priceList, $this->lazio, 'FILA_1', '3157.89');
    $format = priceCalculatorFormat('FILA_1');

    $oneMinute = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, $format, 60)])->clubs[0];
    $ninety = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, $format, 90)])->clubs[0];

    expect($oneMinute->matches->matches)->toBe(19)
        ->and($oneMinute->ledUnitPrice)->toBe('3157.89')
        ->and($oneMinute->ledPrice)->toBe('59999.91')
        ->and(abs((float) $oneMinute->ledPrice - 60000))->toBeLessThanOrEqual(1.0)
        // 1,5 × 59.999,91 = 89.999,865: one rounding on the price of the club, 0.5 up (D-190, D-336).
        ->and($ninety->ledPrice)->toBe('89999.87')
        ->and($ninety->total)->toBe('89999.87');
});

it('M03-CA2: with the contract starting during the season and 10 home matches left, the LED price is cost × minutes × 10', function () {
    $this->edition->update(['calendar_loaded_at' => now()]);
    $rival = Club::factory()->create();
    ClubEnrollment::query()->create(['competition_season_id' => $this->edition->id, 'club_id' => $rival->id]);

    foreach (range(1, 19) as $number) {
        // A home match every second week from 3 September 2051: the last 10 start on 7 January 2052.
        FixtureMatch::factory()->create([
            'matchday_id' => Matchday::query()->create(['competition_season_id' => $this->edition->id, 'stage' => MatchdayStage::Regular, 'number' => $number])->id,
            'home_club_id' => $this->lazio->id,
            'away_club_id' => $rival->id,
            'match_date' => Carbon::parse('2051-09-03')->addWeeks(2 * ($number - 1))->toDateString(),
        ]);
    }

    priceCalculatorLed($this->priceList, $this->lazio, 'FILA_1', '3157.89');

    $quote = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, priceCalculatorFormat('FILA_1'), 90)], '2052-01-07')->clubs[0];

    expect($quote->matches->matches)->toBe(10)
        ->and($quote->matches->fromCalendar)->toBeTrue()
        // 3.157,89 × 1,5 × 10 = 47.368,35
        ->and($quote->ledPrice)->toBe('47368.35');
});

it('D-187: without a calendar a contract from mid-season prorates the standard matches, with a warning', function () {
    priceCalculatorLed($this->priceList, $this->lazio, 'FILA_2', '1000.00');

    // From 1 January to 30 June 2052: 182 of 366 days × 19 = 9,45 → 9 matches.
    $quote = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, priceCalculatorFormat('FILA_2'), 60)], '2052-01-01');

    expect($quote->clubs[0]->matches->matches)->toBe(9)
        ->and($quote->clubs[0]->ledPrice)->toBe('9000.00')
        ->and($quote->warnings)->toHaveCount(1);
});

it('D-051: Jumbo and Domination are priced by their own cost for 1′, once, whatever the pools they consume', function () {
    priceCalculatorLed($this->priceList, $this->lazio, 'DOMINATION', '5105.26');

    $quote = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, priceCalculatorFormat('DOMINATION'), 60)])->clubs[0];

    // 5.105,26 × 19 = 96.999,94
    expect($quote->ledPrice)->toBe('96999.94');
});

/* Partnership (RB-03, RB-04, D-029, D-052). */

it('M03-CA3: the partnership Premium of Lazio stays 135.000 € whatever the start date', function () {
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => priceCalculatorTier('PREMIUM')->id, 'season_price' => '135000.00']);
    $selection = [new ClubSelection($this->lazio, tier: priceCalculatorTier('PREMIUM'))];

    foreach (['2051-07-01', '2051-12-01', '2052-05-01'] as $from) {
        $quote = priceCalculatorQuote($this->season, $selection, $from)->clubs[0];

        expect($quote->partnershipPrice)->toBe('135000.00')
            ->and($quote->ledPrice)->toBe('0.00')
            ->and($quote->total)->toBe('135000.00');
    }
});

it('D-052: the free package costs nothing and needs no price', function () {
    $quote = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, tier: priceCalculatorTier('LIBERA'))])->clubs[0];

    expect($quote->partnershipPrice)->toBe('0.00')
        ->and($quote->total)->toBe('0.00');
});

/* Hospitality (RB-05, D-055, D-147). */

it('M03-CA9: 2 Premium seats of Lazio at the seasonal price P cost 2 × P, with or without a partnership', function () {
    HospitalityPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'hospitality_variant_id' => priceCalculatorVariant('PREMIUM')->id, 'seat_price' => '4250.50']);
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => priceCalculatorTier('TOP')->id, 'season_price' => '200000.00']);
    $seats = [['variant' => priceCalculatorVariant('PREMIUM'), 'seats' => 2]];

    $alone = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, seats: $seats)])->clubs[0];
    $withTier = priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, tier: priceCalculatorTier('TOP'), seats: $seats)])->clubs[0];

    expect($alone->hospitality[0]->price)->toBe('8501.00')
        ->and($alone->hospitalityPrice)->toBe('8501.00')
        ->and($alone->total)->toBe('8501.00')
        ->and($withTier->hospitalityPrice)->toBe('8501.00')
        ->and($withTier->total)->toBe('208501.00');
});

it('D-147: more seats than the maximum of the settings per club are refused, also across variants', function () {
    foreach (['PREMIUM', 'BASE'] as $code) {
        HospitalityPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'hospitality_variant_id' => priceCalculatorVariant($code)->id, 'seat_price' => '1000.00']);
    }

    $seats = [['variant' => priceCalculatorVariant('PREMIUM'), 'seats' => 4], ['variant' => priceCalculatorVariant('BASE'), 'seats' => 3]];

    expect(fn () => priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, seats: $seats)]))
        ->toThrow(CatalogActionNotAllowed::class, 'Massimo 6 posti hospitality per club (SS Lazio)');

    Setting::query()->updateOrCreate(['key' => 'hospitality_max_seats'], ['value' => 4]);

    expect(fn () => priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, seats: [['variant' => priceCalculatorVariant('BASE'), 'seats' => 5]])]))
        ->toThrow(CatalogActionNotAllowed::class, 'Massimo 4 posti');
});

/* A proposal on more clubs (D-103). */

it('D-103: the list total is the sum of the clubs at list prices, each club with its own matches', function () {
    $genoa = priceCalculatorClub($this->edition, 'Genoa CFC', 'GENOA');
    priceCalculatorLed($this->priceList, $this->lazio, 'FILA_1', '3157.89');
    priceCalculatorLed($this->priceList, $genoa, 'FILA_2', '842.11');
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $genoa->id, 'partnership_tier_id' => priceCalculatorTier('OFFICIAL')->id, 'season_price' => '50000.00']);

    $quote = priceCalculatorQuote($this->season, [
        new ClubSelection($this->lazio, priceCalculatorFormat('FILA_1'), 60),
        new ClubSelection($genoa, priceCalculatorFormat('FILA_2'), 120, priceCalculatorTier('OFFICIAL')),
    ]);

    expect($quote->clubs[0]->total)->toBe('59999.91')
        // 842,11 × 2 × 19 = 32.000,18 + 50.000
        ->and($quote->clubs[1]->ledPrice)->toBe('32000.18')
        ->and($quote->clubs[1]->total)->toBe('82000.18')
        ->and($quote->listTotal)->toBe('142000.09')
        ->and($quote->matchCount->totalMatches)->toBe(38);
});

/* What a proposal may use (M03 RB-09, M03-CA4, D-063, D-088). */

it('M03 RB-09: a draft price list cannot price a proposal', function () {
    $season = Season::factory()->forYear(2052)->open()->create();
    $club = priceCalculatorClub(CompetitionSeason::factory()->create(['season_id' => $season->id]), 'Genoa CFC', 'GENOA');
    PriceList::factory()->create(['season_id' => $season->id]);

    expect(fn () => priceCalculatorQuote($season, [new ClubSelection($club, tier: priceCalculatorTier('LIBERA'))], '2052-07-01'))
        ->toThrow(CatalogActionNotAllowed::class, "Il listino della stagione {$season->code} non è pubblicato");
});

it('M03-CA4: an option without a price for the club cannot be priced', function () {
    expect(fn () => priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, priceCalculatorFormat('JUMBO'), 60)]))
        ->toThrow(CatalogActionNotAllowed::class, "Jumbo non è disponibile per SS Lazio nel listino {$this->season->code}");
});

it('D-063, D-088: LED minutes only in steps of 30″ and with a format; a club only once', function () {
    priceCalculatorLed($this->priceList, $this->lazio, 'FILA_1', '3157.89');
    $format = priceCalculatorFormat('FILA_1');

    expect(fn () => priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, $format, 75)]))
        ->toThrow(CatalogActionNotAllowed::class, 'I minuti a partita di SS Lazio vanno a passi di 0′30″')
        ->and(fn () => priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, $format, 0)]))
        ->toThrow(CatalogActionNotAllowed::class)
        ->and(fn () => priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, null, 60)]))
        ->toThrow(CatalogActionNotAllowed::class)
        ->and(fn () => priceCalculatorQuote($this->season, [new ClubSelection($this->lazio, $format, 60), new ClubSelection($this->lazio, $format, 60)]))
        ->toThrow(CatalogActionNotAllowed::class)
        ->and(fn () => priceCalculatorQuote($this->season, []))
        ->toThrow(CatalogActionNotAllowed::class);
});

it('D-328: a club without a league in the season stops the calculation', function () {
    $cup = CompetitionSeason::factory()->create([
        'season_id' => $this->season->id,
        'competition_id' => Competition::factory()->create(['type' => CompetitionType::Cup])->id,
    ]);
    $cupOnly = priceCalculatorClub($cup, 'Club di coppa', 'COPPA');

    expect(fn () => priceCalculatorQuote($this->season, [new ClubSelection($cupOnly, tier: priceCalculatorTier('LIBERA'))]))
        ->toThrow(SportsActionNotAllowed::class)
        ->and(app(PriceCalculator::class)->seasonalLedPrice($this->season, $cupOnly, '1000.00'))->toBeNull();
});

/* Indicative seasonal price (D-340) and the single formula. */

it('D-340: the indicative seasonal price is 1′ per match for the home matches of the whole season', function () {
    $seasonal = app(PriceCalculator::class)->seasonalLedPrice($this->season, $this->lazio, '3157.89');

    expect($seasonal?->price)->toBe('59999.91')
        ->and($seasonal?->matches)->toBe(19)
        ->and($seasonal?->fromCalendar)->toBeFalse();
});

it('D-190, D-336: the LED price is rounded once, to the cent, 0.5 up', function (string $unit, int $seconds, int $matches, string $expected) {
    expect(app(PriceCalculator::class)->ledPrice($unit, $seconds, $matches))->toBe($expected);
})->with([
    'CA1 1′' => ['3157.89', 60, 19, '59999.91'],
    'CA1 1′30″' => ['3157.89', 90, 19, '89999.87'],
    'half cent up' => ['0.01', 30, 1, '0.01'],
    'no matches' => ['0.01', 30, 0, '0.00'],
    '6′30″, 17 matches' => ['1052.63', 390, 17, '116315.62'],
]);

/* Command catalog:price (D-337 point 7). */

it('D-337: the command shows the list prices of a club, read only', function () {
    priceCalculatorLed($this->priceList, $this->lazio, 'FILA_1', '3157.89');
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => priceCalculatorTier('PREMIUM')->id, 'season_price' => '135000.00']);
    HospitalityPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'hospitality_variant_id' => priceCalculatorVariant('PREMIUM')->id, 'seat_price' => '4250.50']);

    expect(Artisan::call('catalog:price', [
        'season' => $this->season->code,
        'club' => 'lazio',
        '--format' => 'FILA_1',
        '--minutes' => '1,5',
        '--tier' => 'PREMIUM',
        '--seats' => ['PREMIUM:2'],
    ]))->toBe(0);

    expect(Artisan::output())
        ->toContain("Listino {$this->season->code} · periodo dal 01/07/2051 al 30/06/2052")
        ->toContain('89.999,87 €')
        ->toContain('135.000,00 €')
        ->toContain('8.501,00 €')
        ->toContain('Totale di listino: 233.500,87 €')
        ->toContain('1ª fila 1′30″ × 3.157,89 € × 19 partite = 89.999,87 €');
});

it('D-337: the command refuses unknown codes, wrong minutes and a draft price list', function () {
    $this->artisan('catalog:price', ['season' => '1999-00', 'club' => 'LAZIO', '--tier' => 'LIBERA'])
        ->expectsOutputToContain('Stagione 1999-00 inesistente')->assertFailed();
    $this->artisan('catalog:price', ['season' => $this->season->code, 'club' => 'LAZIO'])
        ->expectsOutputToContain('Indica almeno un format LED')->assertFailed();
    $this->artisan('catalog:price', ['season' => $this->season->code, 'club' => 'LAZIO', '--format' => 'FILA_1', '--minutes' => 'uno'])
        ->expectsOutputToContain('Minuti non validi: uno')->assertFailed();
    $this->artisan('catalog:price', ['season' => $this->season->code, 'club' => 'LAZIO', '--format' => 'FILA_1', '--minutes' => '1'])
        ->expectsOutputToContain('1ª fila non è disponibile per SS Lazio')->assertFailed();
});
