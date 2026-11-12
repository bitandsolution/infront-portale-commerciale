<?php

use App\Enums\AuditAction;
use App\Enums\MatchStatus;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Summary and recalculation, step 5 of the configurator (M06 §6, §7 RB-03 and RB-12, §9; M06-CA3,
| CA10 as precised by D-373; M03-CA6 for the drafts; D-036, D-045, D-093, D-187, D-253; slice F4-04).
| No new route: the summary is ?passo=5 of proposals.edit, already in the route × role matrix.
|
| Data: a league with the top match KPI and a loaded calendar. From 01/10/2027 to the end of the
| season Bologna plays 3 home matches (2 against Inter, a top match opponent) and Como 2 (1 against
| Inter); the campaign runs from 03/10/2027 to 20/05/2028 (8 months).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create([
        'season_id' => $this->season->id,
        'competition_id' => Competition::factory()->create(['has_top_match_kpi' => true])->id,
        'standard_home_matches' => 19,
        'calendar_loaded_at' => now(),
    ]);

    $this->bologna = summClub($this, 'Bologna FC', 'BOLOGNA', $this->league, '1000.00');
    $this->como = summClub($this, 'Como 1907', 'COMO', $this->league, '2000.00');

    $inter = Club::factory()->topMatch()->create(['name' => 'FC Internazionale', 'code' => 'INTER']);
    $empoli = Club::factory()->create(['name' => 'Empoli FC', 'code' => 'EMPOLI']);

    summMatch($this, 1, $this->bologna, $inter, '2027-09-15'); // before the start: not counted
    summMatch($this, 3, $this->bologna, $inter, '2027-10-03');
    summMatch($this, 5, $this->bologna, $empoli, '2027-11-07');
    summMatch($this, 20, $this->bologna, $inter, '2028-02-10');
    $this->comoFirst = summMatch($this, 4, $this->como, $empoli, '2027-10-17');
    summMatch($this, 36, $this->como, $inter, '2028-05-20');

    $this->agent = User::factory()->agent()->create();
});

function summClub(object $test, string $name, string $code, CompetitionSeason $edition, string $unitPrice): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, 'code' => $code]);
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);
    LedPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'led_format_id' => summFormat('FILA_1')->id, 'unit_price' => $unitPrice]);

    foreach (['FILA_1', 'FILA_2'] as $pool) {
        PoolCapacity::query()->create([
            'season_id' => $test->season->id,
            'club_id' => $club->id,
            'inventory_pool_id' => InventoryPool::query()->where('code', $pool)->firstOrFail()->id,
            'is_enabled' => true,
            'seconds_per_match' => 600,
        ]);
    }

    return $club;
}

function summFormat(string $code): LedFormat
{
    return LedFormat::query()->where('code', $code)->firstOrFail();
}

function summTier(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function summMatch(object $test, int $matchday, Club $home, Club $away, string $date): FixtureMatch
{
    return FixtureMatch::factory()->forEdition($test->league, $matchday)->create([
        'home_club_id' => $home->id,
        'away_club_id' => $away->id,
        'match_date' => $date,
    ]);
}

function summDraft(User $agent): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);

    return Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
}

/**
 * One club as the page sends it.
 *
 * @return array<string, mixed>
 */
function summRow(Club $club, ?string $tier = null, ?string $format = null, int $seconds = 0): array
{
    return [
        'club_id' => $club->id,
        'partnership_tier_id' => $tier !== null ? summTier($tier)->id : null,
        'led_format_id' => $format !== null ? summFormat($format)->id : null,
        'led_seconds' => $seconds,
        'seats' => [],
    ];
}

/**
 * Automatic save of the page.
 *
 * @param  list<array<string, mixed>>|null  $clubs
 * @param  array<string, mixed>  $overrides
 */
function summSave(User $agent, Proposal $proposal, ?array $clubs, array $overrides = []): TestResponse
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    return test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), array_merge([
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
        ], $clubs !== null ? ['clubs' => $clubs] : [], $overrides));
}

/** Bologna 1′00″ and Como 1′30″ of 1st row from 01/10/2027 to the end of the season. */
function summNetwork(object $test): Proposal
{
    $proposal = summDraft($test->agent);
    summSave($test->agent, $proposal, [
        summRow($test->bologna, null, 'FILA_1', 60),
        summRow($test->como, null, 'FILA_1', 90),
    ], ['starts_on' => '2027-10-01'])->assertSessionHasNoErrors();

    return $proposal->refresh();
}

/** Props of the configurator at a step. */
function summPage(User $agent, Proposal $proposal, int $step = 5): array
{
    $props = [];

    test()->actingAs($agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => $step]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    return $props;
}

function summVersion(Proposal $proposal): ProposalVersion
{
    return ProposalVersion::query()->where('proposal_id', $proposal->id)->whereNull('number')->firstOrFail();
}

function summRowOf(Proposal $proposal, Club $club): ProposalVersionClub
{
    return ProposalVersionClub::query()->where('proposal_version_id', summVersion($proposal)->id)->where('club_id', $club->id)->firstOrFail();
}

/* Table and KPI of the campaign (M06 §6, D-093). */

it('M06-CA3: Bologna + Como from 01/10 to the end of the season count the home league matches of the calendar after that date', function () {
    $props = summPage($this->agent, summNetwork($this));

    expect($props['step'])->toBe(5)
        ->and($props['lastStep'])->toBe(6)
        ->and($props['draft']['blocksSummary'])->toBeFalse()
        ->and($props['draft']['clubs'][0])->toMatchArray([
            'name' => 'Bologna FC',
            'formatName' => summFormat('FILA_1')->name,
            'ledLabel' => '1′00″',
            'matches' => 3,
            'spotsPerMatch' => 4,
            'hospitalityLabel' => null,
            'price' => '3.000,00',
        ])
        ->and($props['draft']['clubs'][1])->toMatchArray([
            'name' => 'Como 1907',
            'ledLabel' => '1′30″',
            'matches' => 2,
            'spotsPerMatch' => 6,
            'price' => '6.000,00',
        ])
        ->and($props['draft']['listTotal'])->toBe('9.000,00');
});

it('D-093: the KPI of the campaign come from the server on all the clubs together and are saved on the working version', function () {
    $proposal = summNetwork($this);
    $props = summPage($this->agent, $proposal);

    // Spots = Σ seconds / 15 × matches = 4 × 3 + 6 × 2; top matches: home matches against Inter (D-253).
    expect($props['draft']['campaign'])->toBe([
        'clubs' => 2,
        'totalMatches' => 5,
        'ledMatches' => 5,
        'totalSpots' => 24,
        'months' => 8,
        'range' => 'Ottobre 2027 – Maggio 2028',
        'topMatchesApplicable' => true,
        'topMatches' => '3',
        'fromCalendar' => true,
    ]);

    $version = summVersion($proposal);
    expect($version->campaign_starts_on?->toDateString())->toBe('2027-10-03')
        ->and($version->campaign_months)->toBe(8)
        ->and($version->total_matches)->toBe(5)
        ->and($version->total_spots)->toBe(24)
        ->and($version->top_matches)->toBe(3)
        ->and(auditEventsOf(AuditAction::ProposalDraftUpdated)->last()?->new_values)->toMatchArray([
            'campaign_starts_on' => '2027-10-03',
            'campaign_months' => 8,
            'total_matches' => 5,
            'total_spots' => 24,
            'top_matches' => 3,
        ]);
});

it('D-036: top matches only for competitions with the KPI; without calendar of a club with the KPI they are "n.d."', function () {
    $other = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $genoa = summClub($this, 'Genoa CFC', 'GENOA', $other, '1500.00');
    $proposal = summDraft($this->agent);
    summSave($this->agent, $proposal, [summRow($genoa, null, 'FILA_1', 30)])->assertSessionHasNoErrors();

    $campaign = summPage($this->agent, $proposal)['draft']['campaign'];
    expect($campaign['topMatchesApplicable'])->toBeFalse()
        ->and($campaign['topMatches'])->toBeNull()
        ->and(summVersion($proposal)->top_matches)->toBeNull();

    $this->league->update(['calendar_loaded_at' => null]);
    summSave($this->agent, $proposal, [summRow($genoa, null, 'FILA_1', 30), summRow($this->bologna, null, 'FILA_1', 30)])->assertSessionHasNoErrors();

    $draft = summPage($this->agent, $proposal)['draft'];
    expect($draft['campaign']['topMatches'])->toBe('n.d.')
        ->and($draft['campaign']['fromCalendar'])->toBeFalse()
        ->and(array_column($draft['warnings'], 'text'))->toContain('Top match non disponibili: manca il calendario di un club di Serie A.');
});

/* Warnings (M06 §6, §9; RB-03, D-187, D-382). */

it('RB-03: a club without calendar shows the warning of the standard number, prorated on the period', function () {
    $this->league->update(['calendar_loaded_at' => null]);
    $proposal = summDraft($this->agent);
    summSave($this->agent, $proposal, [summRow($this->bologna, null, 'FILA_1', 60)])->assertSessionHasNoErrors();

    expect(summPage($this->agent, $proposal)['draft']['warnings'])->toBe([
        ['kind' => 'info', 'text' => 'Bologna FC: calendario non caricato, 19 partite in casa dal numero standard della competizione.'],
        ['kind' => 'info', 'text' => 'Top match non disponibili: manca il calendario di un club di Serie A.'],
    ]);

    // 01/10/2027 – 30/06/2028: 274 days of 366, 19 × 274 / 366 = 14,22 → 14 (D-187).
    summSave($this->agent, $proposal, null, ['starts_on' => '2027-10-01'])->assertSessionHasNoErrors();

    expect(summPage($this->agent, $proposal)['draft']['warnings'])->toBe([
        ['kind' => 'info', 'text' => 'Bologna FC: calendario non caricato, 14 partite in casa stimate riproporzionando sul periodo il numero standard (19 a stagione).'],
        ['kind' => 'info', 'text' => 'Top match non disponibili: manca il calendario di un club di Serie A.'],
    ]);
});

it('M06 §9, D-382: residuals changed since the save and a club no longer on sale are listed as warnings to correct', function () {
    $proposal = summNetwork($this);
    InventoryCommitment::factory()->forPool(InventoryPool::query()->where('code', 'FILA_1')->firstOrFail(), 570)
        ->create(['season_id' => $this->season->id, 'club_id' => $this->bologna->id]);
    LedPrice::query()->where('club_id', $this->como->id)->delete();

    $draft = summPage($this->agent, $proposal)['draft'];
    $errors = array_column(array_filter($draft['warnings'], fn (array $warning): bool => $warning['kind'] === 'error'), 'text');

    expect($draft['blocksSummary'])->toBeFalse()
        ->and($errors)->toContain('Bologna FC: I minuti vanno a passi di 0′30″ e non possono superare il residuo (0′30″).')
        ->and($errors)->toHaveCount(2)
        ->and($errors[1])->toStartWith('Como 1907 non è selezionabile nella stagione 2027-28');
});

/* Club without elements (M06-CA10 as precised by D-373). */

it('M06-CA10, D-373: a club without elements blocks the summary, the draft stays saved', function () {
    $proposal = summDraft($this->agent);
    summSave($this->agent, $proposal, [summRow($this->bologna, 'LIBERA'), summRow($this->como, null, 'FILA_1', 30)])->assertSessionHasNoErrors();

    $props = summPage($this->agent, $proposal);
    expect($props['step'])->toBe(4)
        ->and($props['draft']['blocksSummary'])->toBeTrue()
        ->and($props['draft']['clubs'][0]['errors'])->toHaveKey('elements')
        ->and(summRowOf($proposal, $this->bologna)->partnership_tier_id)->toBe(summTier('LIBERA')->id);

    summSave($this->agent, $proposal, [summRow($this->bologna, 'LIBERA', 'FILA_1', 30), summRow($this->como, null, 'FILA_1', 30)])->assertSessionHasNoErrors();

    expect(summPage($this->agent, $proposal)['step'])->toBe(5);
});

it('D-373: a draft without clubs has no summary', function () {
    $proposal = summDraft($this->agent);

    $props = summPage($this->agent, $proposal);
    expect($props['step'])->toBe(4)
        ->and($props['draft']['blocksSummary'])->toBeTrue()
        ->and($props['draft']['campaign'])->toBeNull()
        ->and($props['reopened'])->toBeNull();
});

/* Recalculation at the reopening (RB-12, M03-CA6 for the drafts, D-045). */

it('M03-CA6, RB-12: a reopened draft shows the new list price "da € X a € Y" without writing; the next save updates it', function () {
    $proposal = summNetwork($this);
    expect(summPage($this->agent, $proposal)['reopened'])->toBeNull();

    LedPrice::query()->where('club_id', $this->bologna->id)->update(['unit_price' => '1200.00']);
    $events = auditEventsOf(AuditAction::ProposalDraftUpdated)->count();
    $lockVersion = $proposal->refresh()->lock_version;

    $props = summPage($this->agent, $proposal, 2);
    expect($props['reopened'])->toBe([
        'clubs' => [['name' => 'Bologna FC', 'text' => 'Bologna FC: prezzo ricalcolato da € 3.000,00 a € 3.600,00 (listino aggiornato).']],
        'total' => 'Totale di listino ricalcolato da € 9.000,00 a € 9.600,00.',
    ])
        ->and($props['draft']['listTotal'])->toBe('9.600,00')
        ->and($props['draft']['clubs'][0]['price'])->toBe('3.600,00')
        // Opening the draft writes nothing (the saved prices stay until the next save).
        ->and(summRowOf($proposal, $this->bologna)->club_total)->toBe('3000.00')
        ->and($proposal->refresh()->lock_version)->toBe($lockVersion)
        ->and(auditEventsOf(AuditAction::ProposalDraftUpdated))->toHaveCount($events);

    summSave($this->agent, $proposal, null, ['ends_on' => '2028-06-29'])->assertSessionHasNoErrors();

    expect(summRowOf($proposal, $this->bologna)->club_total)->toBe('3600.00')
        ->and(summVersion($proposal)->list_total)->toBe('9600.00')
        ->and(auditEventsOf(AuditAction::ProposalDraftUpdated)->last()?->new_values)->toMatchArray(['list_total' => '9600.00'])
        ->and(summPage($this->agent, $proposal)['reopened'])->toBeNull();
});

it('RB-12: matches changed in the calendar are the reason of a recalculated price', function () {
    $proposal = summNetwork($this);
    $this->comoFirst->update(['status' => MatchStatus::Cancelled]);

    $props = summPage($this->agent, $proposal);
    expect($props['reopened']['clubs'])->toBe([
        ['name' => 'Como 1907', 'text' => 'Como 1907: prezzo ricalcolato da € 6.000,00 a € 3.000,00 (partite nel periodo cambiate).'],
    ])
        ->and($props['reopened']['total'])->toBe('Totale di listino ricalcolato da € 9.000,00 a € 6.000,00.')
        ->and($props['draft']['campaign']['totalMatches'])->toBe(4);
});
