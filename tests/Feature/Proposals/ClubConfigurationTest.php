<?php

use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Enums\AuditAction;
use App\Enums\CompetitionType;
use App\Enums\MatchesSource;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\HospitalityCapacity;
use App\Models\HospitalityPrice;
use App\Models\HospitalityVariant;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\Territory;
use App\Models\User;
use App\Models\VisibilityType;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Clubs and configuration by club, steps 3 and 4 of the configurator (M06 §4, §5, §7, §8, §9;
| M06-CA1, CA2, CA4, CA5, CA6, CA10 as precised by D-373; M03-CA8; M04-CA5, CA7; D-051…D-055,
| D-063…D-066, D-085, D-088, D-147, D-328, D-372; slice F4-03). No new route: the automatic save
| (proposals.update) and the page (proposals.edit) are already in the route × role matrix.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);

    // Lazio: 1st row switched off (M06-CA2), 2nd row 10′ per match; TOP partnership, Premium seats.
    $this->lazio = confClub($this, 'SS Lazio', 'LAZIO');
    confLedPrices($this, $this->lazio, ['JUMBO', 'DOMINATION', 'FILA_1', 'FILA_2'], '1000.00');
    confPool($this, $this->lazio, 'FILA_1', 1800, enabled: false);
    confPool($this, $this->lazio, 'FILA_2', 600);
    confTier($this, $this->lazio, 'TOP', '180000.00', 1);
    confTier($this, $this->lazio, 'PREMIUM', '135000.00', 2);
    confSeats($this, $this->lazio, 'PREMIUM', '1500.00', 300);
    confSeats($this, $this->lazio, 'BASE', '800.00', 3);

    // Udinese: 1st row at C = 3.157,89 € per 1′ per match (M06-CA4).
    $this->udinese = confClub($this, 'Udinese Calcio', 'UDINESE');
    confLedPrices($this, $this->udinese, ['FILA_1'], '3157.89');
    confPool($this, $this->udinese, 'FILA_1', 1800);
    confPool($this, $this->udinese, 'FILA_2', 1800);

    $this->agent = User::factory()->agent()->create();
});

function confClub(object $test, string $name, string $code, ?CompetitionSeason $edition = null): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, 'code' => $code]);
    ClubEnrollment::query()->create(['competition_season_id' => ($edition ?? $test->league)->id, 'club_id' => $club->id]);

    return $club;
}

function confFormat(string $code): LedFormat
{
    return LedFormat::query()->where('code', $code)->firstOrFail();
}

function confTierModel(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function confVariant(string $code): HospitalityVariant
{
    return HospitalityVariant::query()->where('code', $code)->firstOrFail();
}

function confLedPrices(object $test, Club $club, array $codes, string $price): void
{
    foreach ($codes as $code) {
        LedPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'led_format_id' => confFormat($code)->id, 'unit_price' => $price]);
    }
}

function confPool(object $test, Club $club, string $pool, int $seconds, bool $enabled = true): void
{
    PoolCapacity::query()->create([
        'season_id' => $test->season->id,
        'club_id' => $club->id,
        'inventory_pool_id' => InventoryPool::query()->where('code', $pool)->firstOrFail()->id,
        'is_enabled' => $enabled,
        'seconds_per_match' => $seconds,
    ]);
}

function confTier(object $test, Club $club, string $code, string $price, int $quantity): void
{
    PartnershipPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'partnership_tier_id' => confTierModel($code)->id, 'season_price' => $price]);
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => confTierModel($code)->id, 'is_enabled' => true, 'quantity' => $quantity]);
}

function confSeats(object $test, Club $club, string $code, string $price, int $seats): void
{
    HospitalityPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'hospitality_variant_id' => confVariant($code)->id, 'seat_price' => $price]);
    HospitalityCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'hospitality_variant_id' => confVariant($code)->id, 'is_enabled' => true, 'seats' => $seats]);
}

function confDraft(User $agent): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);

    return Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
}

/**
 * One club as the page sends it.
 *
 * @param  array<string, int>  $seats  seats by variant code
 * @return array<string, mixed>
 */
function confRow(Club $club, ?string $tier = null, ?string $format = null, int $seconds = 0, array $seats = []): array
{
    return [
        'club_id' => $club->id,
        'partnership_tier_id' => $tier !== null ? confTierModel($tier)->id : null,
        'led_format_id' => $format !== null ? confFormat($format)->id : null,
        'led_seconds' => $seconds,
        'seats' => array_map(fn (string $code, int $count): array => ['variant_id' => confVariant($code)->id, 'seats' => $count], array_keys($seats), array_values($seats)),
    ];
}

/**
 * Automatic save of the page with the given clubs (and header overrides).
 *
 * @param  list<array<string, mixed>>|null  $clubs  null = the clubs are not sent
 * @param  array<string, mixed>  $overrides
 */
function confSave(User $agent, Proposal $proposal, ?array $clubs, array $overrides = []): TestResponse
{
    $proposal->refresh();
    $version = $proposal->workingVersion;
    $payload = array_merge([
        'lock_version' => $proposal->lock_version,
        'client_id' => $proposal->client_id,
        'season_id' => $proposal->season_id,
        'starts_on' => $version?->starts_on?->toDateString(),
        'ends_on' => $version?->ends_on?->toDateString(),
    ], $clubs !== null ? ['clubs' => $clubs] : [], $overrides);

    return test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), $payload);
}

function confRowOf(Proposal $proposal, Club $club): ?ProposalVersionClub
{
    return ProposalVersionClub::query()
        ->where('proposal_version_id', $proposal->workingVersion()->value('id'))
        ->where('club_id', $club->id)
        ->with('seats')
        ->first();
}

/** Errors of the clubs of the draft as the page receives them, by club name. */
function confPageErrors(User $agent, Proposal $proposal): array
{
    $errors = [];

    test()->actingAs($agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$errors) {
            foreach ($page->toArray()['props']['draft']['clubs'] as $club) {
                $errors[$club['name']] = $club['errors'];
            }
        });

    return $errors;
}

/* Step 3: clubs (M06-CA1, RB-09, D-085). */

it('M06-CA1: step 3 offers only the Infront clubs with a published list and a price in the season', function () {
    confClub($this, 'Genoa CFC', 'GENOA'); // enrolled, no price: not selectable (D-190)
    $rival = Club::factory()->create(['name' => 'Avversario FC', 'code' => 'AVVERSARIO', 'is_infront' => false]);
    ClubEnrollment::query()->create(['competition_season_id' => $this->league->id, 'club_id' => $rival->id]);
    $proposal = confDraft($this->agent);

    $this->actingAs($this->agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 3]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('step', 3)
            ->where('lastStep', 4)
            ->has('clubOptions', 2)
            ->where('clubOptions.0.name', 'SS Lazio')
            ->where('clubOptions.1.name', 'Udinese Calcio')
            ->where('clubOptions.0.competition', $this->league->competition->name)
            ->where('ledStepSeconds', 30)
            ->where('hospitalityMaxSeats', 6));
});

it('M06-CA2, M04-CA3: for Lazio with the 1st row switched off the formats 1st row, Jumbo and Domination are not offered', function () {
    $proposal = confDraft($this->agent);

    $this->actingAs($this->agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('clubOptions.0.formats', 1)
            ->where('clubOptions.0.formats.0.name', confFormat('FILA_2')->name)
            ->where('clubOptions.0.formats.0.remaining', 600)
            ->where('clubOptions.0.formats.0.remainingLabel', '10′00″'));
});

it('FL-02, D-071: a sold out tier stays visible but not selectable; the free package has no counter', function () {
    InventoryCommitment::factory()->forTier(confTierModel('TOP'))->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id]);
    $proposal = confDraft($this->agent);

    $this->actingAs($this->agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertInertia(function (Assert $page) {
            $tiers = collect($page->toArray()['props']['clubOptions'][0]['tiers'])->keyBy('name');

            expect($tiers[confTierModel('LIBERA')->name])->toMatchArray(['isFree' => true, 'status' => 'available', 'remaining' => null])
                ->and($tiers[confTierModel('TOP')->name])->toMatchArray(['status' => 'sold_out', 'statusLabel' => 'Esaurito', 'remaining' => 0])
                ->and($tiers[confTierModel('PREMIUM')->name])->toMatchArray(['status' => 'available', 'remaining' => 2, 'price' => '135.000,00'])
                ->and($tiers->has(confTierModel('OFFICIAL')->name))->toBeFalse();
        });
});

/* Prices from the server at every save (M06-CA4, RB-04…RB-07, D-054, D-190). */

it('M06-CA4: 1′30″ of 1st row Udinese at cost C for N matches costs C × 1,5 × N, calculated by the server at the save', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->udinese, 'LIBERA', 'FILA_1', 90)])->assertSessionHasNoErrors();

    $row = confRowOf($proposal, $this->udinese);
    // 3.157,89 × 1,5 × 19 = 89.999,865 → rounded once on the club price (D-190, D-336).
    expect($row?->home_matches)->toBe(19)
        ->and($row?->matches_source)->toBe(MatchesSource::Standard)
        ->and($row?->led_unit_price)->toBe('3157.89')
        ->and($row?->led_price)->toBe('89999.87')
        ->and($row?->partnership_price)->toBe('0.00')
        ->and($row?->club_total)->toBe('89999.87')
        ->and($row?->price_list_id)->toBe($this->priceList->id)
        ->and($proposal->workingVersion()->value('list_total'))->toBe('89999.87');

    $this->actingAs($this->agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.listTotal', '89.999,87')
            ->where('draft.clubs.0.price', '89.999,87')
            ->where('draft.clubs.0.matches', 19)
            ->where('draft.clubs.0.spots', 114)
            ->where('draft.clubs.0.errors', []));
});

it('RB-05, RB-06, RB-07: partnership at the fixed price, hospitality by seat, the total is the sum of the clubs', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [
        confRow($this->lazio, 'TOP', 'FILA_2', 60, ['PREMIUM' => 2, 'BASE' => 1]),
        confRow($this->udinese, null, 'FILA_1', 30),
    ])->assertSessionHasNoErrors();

    $lazio = confRowOf($proposal, $this->lazio);
    $seats = $lazio?->seats->keyBy('hospitality_variant_id');

    expect($lazio?->sort_order)->toBe(0)
        ->and($lazio?->led_price)->toBe('19000.00')
        ->and($lazio?->partnership_price)->toBe('180000.00')
        ->and($lazio?->hospitality_price)->toBe('3800.00')
        ->and($lazio?->club_total)->toBe('202800.00')
        ->and($seats?->get(confVariant('PREMIUM')->id)?->price)->toBe('3000.00')
        ->and($seats?->get(confVariant('BASE')->id)?->seat_price)->toBe('800.00')
        ->and(confRowOf($proposal, $this->udinese)?->club_total)->toBe('29999.96')
        ->and($proposal->workingVersion()->value('list_total'))->toBe('232799.96');
});

it('RB-02: changing the period without sending the clubs counts the matches and the prices again', function () {
    $proposal = confDraft($this->agent);
    confSave($this->agent, $proposal, [confRow($this->lazio, null, 'FILA_2', 60)]);

    confSave($this->agent, $proposal, null, ['starts_on' => '2028-01-01'])->assertSessionHasNoErrors();

    $row = confRowOf($proposal, $this->lazio);
    expect($row?->home_matches)->toBeLessThan(19)->toBeGreaterThan(0)
        ->and($row?->led_price)->toBe(number_format(1000 * (int) $row?->home_matches, 2, '.', ''))
        ->and($proposal->workingVersion()->value('list_total'))->toBe($row?->club_total);
});

it('M06-CA6, D-086: the configurator has no discount field for the agent', function () {
    $proposal = confDraft($this->agent);
    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP')]);

    $this->actingAs($this->agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertInertia(function (Assert $page) {
            $json = json_encode($page->toArray()['props']['draft']);

            expect($json)->not->toContain('discount')->not->toContain('final_total');
        });
});

/* Refused input (M06-CA5, M04-CA5, M04-CA7, M03-CA8). */

it('M06-CA5, M04-CA5: minutes not in steps of 30″ (1′15″) are refused and nothing is written', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->udinese, null, 'FILA_1', 75)])
        ->assertSessionHasErrors(["clubs.{$this->udinese->id}.led_seconds"]);

    expect(confRowOf($proposal, $this->udinese))->toBeNull()
        ->and($proposal->refresh()->lock_version)->toBe(0);
});

it('M06-CA5, M04-CA5, RB-10: minutes beyond the residual are refused', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->lazio, null, 'FILA_2', 630)])
        ->assertSessionHasErrors(["clubs.{$this->lazio->id}.led_seconds" => 'I minuti vanno a passi di 0′30″ e non possono superare il residuo (10′00″).']);

    expect(confRowOf($proposal, $this->lazio))->toBeNull();

    confSave($this->agent, $proposal, [confRow($this->lazio, null, 'FILA_2', 600)])->assertSessionHasNoErrors();
    expect(confRowOf($proposal, $this->lazio)?->led_seconds_per_match)->toBe(600);
});

it('M03-CA8, M04-CA7, M06-CA5: 7 hospitality seats for the same club are not saved', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->lazio, null, null, 0, ['PREMIUM' => 5, 'BASE' => 2])])
        ->assertSessionHasErrors(["clubs.{$this->lazio->id}.seats" => 'Massimo 6 posti per club.']);

    expect(confRowOf($proposal, $this->lazio))->toBeNull();

    confSave($this->agent, $proposal, [confRow($this->lazio, null, null, 0, ['PREMIUM' => 4, 'BASE' => 2])])->assertSessionHasNoErrors();
    expect(confRowOf($proposal, $this->lazio)?->seats)->toHaveCount(2);
});

it('RB-10: seats beyond the residual of the variant are refused', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->lazio, null, null, 0, ['BASE' => 4])])
        ->assertSessionHasErrors(["clubs.{$this->lazio->id}.seats.".confVariant('BASE')->id]);
});

it('FL-02, D-071: a sold out tier or a hidden format cannot be chosen', function () {
    InventoryCommitment::factory()->forTier(confTierModel('TOP'))->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id]);
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP', 'FILA_1', 60)])
        ->assertSessionHasErrors([
            "clubs.{$this->lazio->id}.tier" => 'Il pacchetto '.confTierModel('TOP')->name.' è esaurito: scegline un altro.',
            "clubs.{$this->lazio->id}.led_format",
        ]);

    expect(confRowOf($proposal, $this->lazio))->toBeNull();
});

it('RB-09: a club that is not selectable cannot be added', function () {
    $genoa = confClub($this, 'Genoa CFC', 'GENOA');
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($genoa)])
        ->assertSessionHasErrors(["clubs.{$genoa->id}.club"]);
});

it('a value that does not exist in the catalogue is refused', function () {
    $proposal = confDraft($this->agent);
    $row = confRow($this->lazio);
    $row['partnership_tier_id'] = 999999;

    confSave($this->agent, $proposal, [$row])->assertSessionHasErrors(['clubs']);
});

/* The draft is always saved; the errors are collected club by club (D-373, M06 §8, §9). */

it('M06-CA10, D-373: a club without elements is saved with the error "configure at least one element"', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->lazio, 'LIBERA'), confRow($this->udinese)])->assertSessionHasNoErrors();

    expect(confRowOf($proposal, $this->lazio))->not->toBeNull()
        ->and(confPageErrors($this->agent, $proposal))->toBe([
            'SS Lazio' => ['elements' => 'Configura almeno un elemento (un pacchetto diverso da Libera, la visibilità LED o l\'hospitality) oppure rimuovi il club.'],
            'Udinese Calcio' => ['elements' => 'Configura almeno un elemento (un pacchetto diverso da Libera, la visibilità LED o l\'hospitality) oppure rimuovi il club.'],
        ]);
});

it('M06 §9: choices already saved that are no longer valid stay in the draft with their errors, every club evaluated', function () {
    // A league with the calendar loaded and no home match in the period: LED with zero matches.
    $empty = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'calendar_loaded_at' => now()]);
    $como = confClub($this, 'Como 1907', 'COMO', $empty);
    confLedPrices($this, $como, ['FILA_2'], '500.00');
    confPool($this, $como, 'FILA_2', 600);

    $proposal = confDraft($this->agent);
    confSave($this->agent, $proposal, [
        confRow($this->lazio, 'TOP', 'FILA_2', 300, ['BASE' => 3]),
        confRow($this->udinese, null, 'FILA_1', 60),
        confRow($como, null, 'FILA_2', 60),
    ])->assertSessionHasNoErrors();

    // Meanwhile other proposals are approved: TOP and Base seats sold out, 2nd row Lazio down to 2′.
    InventoryCommitment::factory()->forTier(confTierModel('TOP'))->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id]);
    InventoryCommitment::factory()->forVariant(confVariant('BASE'), 3)->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id]);
    InventoryCommitment::factory()->forPool(InventoryPool::query()->where('code', 'FILA_2')->firstOrFail(), 480)->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id]);

    $errors = confPageErrors($this->agent, $proposal);

    expect(array_keys($errors['SS Lazio']))->toBe(['tier', 'led_seconds', 'seats.'.confVariant('BASE')->id])
        ->and($errors['SS Lazio']['led_seconds'])->toContain('(2′00″)')
        ->and($errors['Udinese Calcio'])->toBe([])
        ->and($errors['Como 1907'])->toBe(['matches' => 'Nessuna partita in casa nel periodo: togli la visibilità LED o cambia il periodo.']);

    // Saving something else keeps the invalid choices with their errors (not refused).
    confSave($this->agent, $proposal, [
        confRow($this->lazio, 'TOP', 'FILA_2', 300, ['BASE' => 3]),
        confRow($this->udinese, null, 'FILA_1', 90),
        confRow($como, null, 'FILA_2', 60),
    ])->assertSessionHasNoErrors();

    expect(confRowOf($proposal, $this->udinese)?->led_seconds_per_match)->toBe(90)
        ->and(confRowOf($proposal, $this->lazio)?->partnership_tier_id)->toBe(confTierModel('TOP')->id);
});

it('D-328: a club not enrolled in a league of the season is reported and not priced, the others are', function () {
    $cup = CompetitionSeason::factory()->create([
        'season_id' => $this->season->id,
        'competition_id' => Competition::factory()->create(['type' => CompetitionType::Cup])->id,
    ]);
    $proposal = confDraft($this->agent);
    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP'), confRow($this->udinese, null, 'FILA_1', 60)]);

    // The enrolment of Lazio moves from the league to a cup only.
    ClubEnrollment::query()->where('club_id', $this->lazio->id)->update(['competition_season_id' => $cup->id]);

    $result = app(DraftEvaluator::class)->evaluate($this->season, '2027-07-01', '2028-06-30', [
        new DraftClubData($this->lazio->id, confTierModel('TOP')->id),
        new DraftClubData($this->udinese->id, null, confFormat('FILA_1')->id, 60),
    ]);

    expect($result->clubs[0]->errors['club'])->toContain('non è iscritto a un campionato')
        ->and($result->clubs[0]->quote)->toBeNull()
        ->and($result->clubs[1]->errors)->toBe([])
        ->and($result->listTotal)->toBe($result->clubs[1]->total());
});

it('M06 §4: changing season reports the clubs that are not selectable in the new one', function () {
    $proposal = confDraft($this->agent);
    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP')]);
    $next = Season::factory()->forYear(2028)->create();

    confSave($this->agent, $proposal, null, ['season_id' => $next->id, 'starts_on' => '2028-07-01', 'ends_on' => '2029-06-30'])
        ->assertSessionHasNoErrors();

    expect(confRowOf($proposal, $this->lazio)?->club_total)->toBeNull()
        ->and($proposal->workingVersion()->value('list_total'))->toBe('0.00')
        ->and(confPageErrors($this->agent, $proposal)['SS Lazio'])->toHaveKey('club');
});

/* Rows, removal and audit (M06 §5 "Rimuovi club", D-372, D-085). */

it('D-372: a save writes one event with the changed clubs and the list total; a removed club is written as null', function () {
    $proposal = confDraft($this->agent);

    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP', 'FILA_2', 60, ['PREMIUM' => 2])]);

    $event = soleAuditEvent(AuditAction::ProposalDraftUpdated);
    expect($event->new_values)->toBe([
        'club_LAZIO' => 'TOP | FILA_2 1′00″ | PREMIUM 2',
        'list_total' => '202000.00',
    ])->and($event->old_values)->toBe(['club_LAZIO' => null, 'list_total' => '0.00']);

    confSave($this->agent, $proposal, [confRow($this->udinese, null, 'FILA_1', 30)]);

    $last = auditEventsOf(AuditAction::ProposalDraftUpdated)->last();
    expect($last?->new_values)->toMatchArray(['club_UDINESE' => '— | FILA_1 0′30″ | —', 'club_LAZIO' => null])
        ->and(confRowOf($proposal, $this->lazio))->toBeNull()
        ->and($proposal->refresh()->lock_version)->toBe(2);

    // The same clubs again: nothing written, no event (D-372).
    confSave($this->agent, $proposal, [confRow($this->udinese, null, 'FILA_1', 30)]);
    expect(auditEventsOf(AuditAction::ProposalDraftUpdated))->toHaveCount(2);
});

it('D-085: territory and visibility type of a row are the only active ones, never chosen by the agent', function () {
    $proposal = confDraft($this->agent);
    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP')]);

    $row = confRowOf($proposal, $this->lazio);
    expect(Territory::query()->find($row?->territory_id)?->code)->toBe('ITALIA')
        ->and(VisibilityType::query()->find($row?->visibility_type_id)?->code)->toBe('CLASSICA');
});

it('M06 §5: removing a seat variant or the LED clears them from the row', function () {
    $proposal = confDraft($this->agent);
    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP', 'FILA_2', 60, ['PREMIUM' => 2, 'BASE' => 1])]);
    confSave($this->agent, $proposal, [confRow($this->lazio, 'TOP', null, 0, ['PREMIUM' => 2])]);

    $row = confRowOf($proposal, $this->lazio);
    expect($row?->led_format_id)->toBeNull()
        ->and($row?->led_seconds_per_match)->toBeNull()
        ->and($row?->led_price)->toBe('0.00')
        ->and($row?->seats)->toHaveCount(1)
        ->and($row?->club_total)->toBe('183000.00');
});
