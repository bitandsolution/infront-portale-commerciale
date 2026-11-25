<?php

use App\Domain\Content\Actions\SetClubKpis;
use App\Domain\Content\Data\ClubFacts;
use App\Domain\Content\Data\ClubKpiSet;
use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\CompositionInput;
use App\Domain\Content\Data\KpiInput;
use App\Domain\Content\Data\VariantFacts;
use App\Domain\Content\Services\ClubKpis;
use App\Domain\Content\Services\DocumentComposer;
use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Data\RecapRow;
use App\Enums\DocumentLayout;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Enums\SlideDocumentType;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\Document;
use App\Models\InventoryPool;
use App\Models\KpiDefinition;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakePdfRenderer;

/*
| KPI in the documents (M08 F-06, RB-04, RB-05, §10, CA6, CA7; D-111, D-204, D-251, D-414, D-490,
| D-493, D-500…D-503; slice F6-03): ClubKpis reads the KPI in force of the clubs of a document and the
| network sums, the document keeps them in its KPI snapshot, the composition lists the clubs without
| the KPI their document type uses (simulator, detail of the group and step "Documento" for who
| approves, page model). The layouts that draw the KPI come with F7 (D-486). The queue is synchronous
| and the renderer is FakePdfRenderer, as in DocumentGenerationTest.
*/

/** The 8 KPI summed in the network (D-490). */
const DKPI_AGGREGATED = ['FANBASE_ITALY', 'ANNUAL_ATTENDANCE', 'HOME_AUDIENCE', 'FACEBOOK_FOLLOWERS', 'INSTAGRAM_FOLLOWERS', 'X_FOLLOWERS', 'YOUTUBE_FOLLOWERS', 'TIKTOK_FOLLOWERS'];

/** The 5 KPI used only by the club document. */
const DKPI_CLUB_ONLY = ['AVERAGE_MATCH_FANS', 'DEDICATED_BROADCASTS', 'SEASON_TICKETS', 'DIGITAL_USERS', 'DEM_CONTACTS'];

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->league->competition->update(['tier' => 1]);
    $this->tier = PartnershipTier::query()->where('code', 'TOP')->firstOrFail();
    $this->format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();
    $this->pool = InventoryPool::query()->where('code', 'FILA_2')->firstOrFail();

    $this->lazio = dkpiClub($this, 'SS Lazio', 'LAZIO');
    $this->roma = dkpiClub($this, 'AS Roma', 'ROMA');
    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
});

/** An Infront club of the Serie A with the tier TOP at 155,680.00 € and the 2nd row at 1,000.00 € per minute. */
function dkpiClub(object $test, string $name, string $code): Club
{
    $club = Club::factory()->infront()->create([
        'name' => $name,
        'code' => $code,
        'short_name' => ucfirst(strtolower($code)),
        'logo_file_id' => storeTestFile(FilePurpose::ClubLogo, null, null, 'logo.png')->id,
    ]);
    ClubEnrollment::query()->create(['competition_season_id' => $test->league->id, 'club_id' => $club->id]);

    LedPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'led_format_id' => $test->format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'inventory_pool_id' => $test->pool->id, 'is_enabled' => true, 'seconds_per_match' => 600]);
    PartnershipPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'partnership_tier_id' => $test->tier->id, 'season_price' => '155680.00']);
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => $test->tier->id, 'is_enabled' => true, 'quantity' => 2]);

    return $club;
}

/**
 * Saves KPI of a club with SetClubKpis, source "Nielsen" and period "Stagione 2026-27".
 *
 * @param  array<string, int|null>  $values  KPI code → value (null removes it)
 */
function dkpiSet(Season $season, Club $club, array $values): void
{
    $inputs = [];

    foreach ($values as $code => $value) {
        $inputs[] = new KpiInput(
            (int) KpiDefinition::query()->where('code', $code)->value('id'),
            $value,
            $value !== null ? 'Nielsen' : null,
            $value !== null ? 'Stagione 2026-27' : null,
        );
    }

    app(SetClubKpis::class)->handle($season, $club, $inputs, User::factory()->manager()->create());
}

/**
 * Every KPI of a club, the value given increased by the position of the KPI.
 */
function dkpiAll(Season $season, Club $club, int $base): void
{
    $values = [];

    foreach ([...DKPI_AGGREGATED, ...DKPI_CLUB_ONLY] as $index => $code) {
        $values[$code] = $base + $index;
    }

    dkpiSet($season, $club, $values);
}

/** Cover, recap and closing of a club (club document). */
function dkpiClubSlides(object $test, Club $club): void
{
    Slide::factory()->forSeason($test->season)->forClub($club)->section('INTRO')->layout(DocumentLayout::Cover)->create(['title' => 'Copertina '.$club->short_name]);
    Slide::factory()->forSeason($test->season)->forClub($club)->section('RECAP')->layout(DocumentLayout::ClubRecap)->document(SlideDocumentType::Club)->create(['title' => 'Recap '.$club->short_name]);
    Slide::factory()->forSeason($test->season)->forClub($club)->section('CLOSING')->create(['title' => 'Contatti '.$club->short_name]);
}

/** Cover, recap and closing of Infront (network document). */
function dkpiNetworkSlides(object $test): void
{
    Slide::factory()->forSeason($test->season)->section('INTRO')->layout(DocumentLayout::Cover)->create(['title' => 'Copertina network']);
    Slide::factory()->forSeason($test->season)->section('RECAP')->layout(DocumentLayout::NetworkRecap)->document(SlideDocumentType::Network)->create(['title' => 'Recap network']);
    Slide::factory()->forSeason($test->season)->section('CLOSING')->create(['title' => 'Contatti Infront']);
}

/**
 * A group sent for approval, one variant per configuration (club id → TOP and seconds of the 2nd row).
 *
 * @param  list<array<int, array{tier?: bool, seconds?: int}>>  $variants
 */
function dkpiGroup(User $agent, array $variants): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create(['company_name' => 'Caffè Rossi S.p.A.']);
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $first = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    dkpiSave($agent, $first, $variants[0]);

    foreach (array_slice($variants, 1) as $clubs) {
        test()->actingAs($agent)
            ->from(route('proposals.edit', ['proposal' => $first, 'passo' => 5]))
            ->post(route('proposals.variants.store', $first), ['step' => 5])
            ->assertSessionHasNoErrors();
        $variant = Proposal::query()->where('proposal_group_id', $first->proposal_group_id)->latest('id')->firstOrFail();
        dkpiSave($agent, $variant, $clubs);
    }

    test()->actingAs($agent)->post(route('proposals.groups.submit', $first->proposal_group_id))->assertSessionHasNoErrors();

    return $first->refresh();
}

/**
 * @param  array<int, array{tier?: bool, seconds?: int}>  $clubs
 */
function dkpiSave(User $agent, Proposal $proposal, array $clubs): void
{
    $proposal->refresh();
    $version = $proposal->workingVersion;
    $rows = [];

    foreach ($clubs as $clubId => $choice) {
        $seconds = $choice['seconds'] ?? 0;
        $rows[] = [
            'club_id' => $clubId,
            'partnership_tier_id' => ($choice['tier'] ?? false) ? PartnershipTier::query()->where('code', 'TOP')->value('id') : null,
            'led_format_id' => $seconds > 0 ? LedFormat::query()->where('code', 'FILA_2')->value('id') : null,
            'led_seconds' => $seconds,
            'seats' => [],
        ];
    }

    test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => $rows,
        ])
        ->assertSessionHasNoErrors();
}

function dkpiCurrent(Proposal $proposal): Document
{
    return Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();
}

function dkpiRenderer(): FakePdfRenderer
{
    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);

    return $renderer;
}

/**
 * The composition of a proposal built by hand: club → tier of its competition, TOP and the 2nd row.
 *
 * @param  array<int, int>  $clubs  club id → tier
 */
function dkpiCompose(Season $season, array $clubs): Composition
{
    $facts = [];

    foreach ($clubs as $clubId => $tier) {
        $facts[] = new ClubFacts($clubId, 'TOP', true, 'FILA_2', false, $tier);
    }

    return app(DocumentComposer::class)->compose(new CompositionInput($season, [new VariantFacts('A', $facts)]));
}

/**
 * Names of the KPI, in the order of the definitions (club group first, then digital).
 *
 * @param  list<string>  $codes
 */
function dkpiNames(array $codes): string
{
    return implode(', ', KpiDefinition::query()->activeOrdered()->whereIn('code', $codes)->pluck('name')->all());
}

it('M08 F-06, RB-05: the network sums count only the clubs with the value and list the missing ones', function () {
    dkpiSet($this->season, $this->lazio, ['FANBASE_ITALY' => 1_800_000, 'INSTAGRAM_FOLLOWERS' => 2_100_000, 'SEASON_TICKETS' => 30_000]);
    dkpiSet($this->season, $this->roma, ['FANBASE_ITALY' => 1_250_000]);

    $set = app(ClubKpis::class)->of($this->season, [$this->lazio->id => 1, $this->roma->id => 1]);
    $network = $set->network();

    expect(array_keys($network))->toBe(DKPI_AGGREGATED)
        ->and($network['FANBASE_ITALY']->sum)->toBe(3_050_000)
        ->and($network['FANBASE_ITALY']->clubIds)->toBe([$this->lazio->id, $this->roma->id])
        ->and($network['FANBASE_ITALY']->missingClubIds)->toBe([])
        ->and($network['INSTAGRAM_FOLLOWERS']->sum)->toBe(2_100_000)
        ->and($network['INSTAGRAM_FOLLOWERS']->missingClubIds)->toBe([$this->roma->id])
        ->and($network['TIKTOK_FOLLOWERS']->sum)->toBe(0)
        ->and($network['TIKTOK_FOLLOWERS']->clubIds)->toBe([])
        ->and($network['TIKTOK_FOLLOWERS']->missingClubIds)->toBe([$this->lazio->id, $this->roma->id])
        // Season tickets are not summed in the network (D-490).
        ->and($network)->not->toHaveKey('SEASON_TICKETS')
        ->and($set->clubs[$this->lazio->id]->kpis['SEASON_TICKETS']->value)->toBe(30_000)
        ->and($set->clubs[$this->lazio->id]->kpis['FANBASE_ITALY']->source)->toBe('Nielsen')
        ->and($set->clubs[$this->lazio->id]->kpis['FANBASE_ITALY']->period)->toBe('Stagione 2026-27');
});

it('M08 RB-04: only the values of the season of the document and of the active KPI are in force', function () {
    $next = Season::factory()->forYear(2028)->create();
    $edition = CompetitionSeason::factory()->create(['season_id' => $next->id]);
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $this->lazio->id]);
    dkpiSet($this->season, $this->lazio, ['FANBASE_ITALY' => 1_800_000, 'X_FOLLOWERS' => 900_000]);
    dkpiSet($next, $this->lazio, ['FANBASE_ITALY' => 2_000_000]);
    KpiDefinition::query()->where('code', 'X_FOLLOWERS')->update(['is_active' => false]);

    $set = app(ClubKpis::class)->of($this->season, [$this->lazio->id => 1]);

    expect(array_keys($set->clubs[$this->lazio->id]->kpis))->toBe(['FANBASE_ITALY'])
        ->and($set->clubs[$this->lazio->id]->kpis['FANBASE_ITALY']->value)->toBe(1_800_000)
        ->and($set->definitions)->toHaveCount(12)
        ->and($set->network())->not->toHaveKey('X_FOLLOWERS');
});

it('D-251, D-501: the snapshot has the sums of all the clubs and of every competition present', function () {
    $empoli = Club::factory()->infront()->create(['name' => 'Empoli FC']);
    $serieB = CompetitionSeason::factory()->create(['season_id' => $this->season->id]);
    $serieB->competition->update(['tier' => 2]);
    ClubEnrollment::query()->create(['competition_season_id' => $serieB->id, 'club_id' => $empoli->id]);
    dkpiSet($this->season, $this->lazio, ['FANBASE_ITALY' => 1_800_000]);
    dkpiSet($this->season, $this->roma, ['FANBASE_ITALY' => 1_250_000]);
    dkpiSet($this->season, $empoli, ['FANBASE_ITALY' => 150_000, 'HOME_AUDIENCE' => 40_000]);

    $set = app(ClubKpis::class)->of($this->season, [$this->lazio->id => 1, $empoli->id => 2, $this->roma->id => 1]);
    $snapshot = $set->snapshot();
    $network = $snapshot['network'];

    expect($set->tiers())->toBe([1, 2])
        ->and(array_map('strval', array_keys($network)))->toBe([ClubKpiSet::ALL_CLUBS, '1', '2'])
        ->and($network[ClubKpiSet::ALL_CLUBS]['FANBASE_ITALY']['sum'])->toBe(3_200_000)
        ->and($network[1]['FANBASE_ITALY']['sum'])->toBe(3_050_000)
        ->and($network[1]['FANBASE_ITALY']['clubs'])->toBe([$this->lazio->id, $this->roma->id])
        ->and($network[2]['FANBASE_ITALY']['sum'])->toBe(150_000)
        ->and($network[2]['HOME_AUDIENCE'])->toBe(['name' => 'Audience casalinga cumulata', 'sum' => 40_000, 'clubs' => [$empoli->id], 'missing' => []])
        ->and($network[1]['HOME_AUDIENCE']['missing'])->toBe([$this->lazio->id, $this->roma->id])
        ->and(array_keys($snapshot['clubs']))->toBe([$this->lazio->id, $empoli->id, $this->roma->id])
        ->and($snapshot['clubs'][$empoli->id]['name'])->toBe('Empoli FC')
        ->and($snapshot['clubs'][$empoli->id]['competition_tier'])->toBe(2)
        ->and($snapshot['clubs'][$empoli->id]['kpis']['HOME_AUDIENCE'])->toBe([
            'name' => 'Audience casalinga cumulata',
            'value' => 40_000,
            'source' => 'Nielsen',
            'period' => 'Stagione 2026-27',
            'updated_at' => '2027-05-24T08:00:00+00:00',
        ])
        ->and($snapshot['clubs'][$empoli->id]['missing'])->toHaveCount(11)
        ->and($snapshot['clubs'][$empoli->id]['missing'])->not->toContain('FANBASE_ITALY');
});

it('D-493, D-500: the generated document copies the KPI in force of its clubs in the snapshot', function () {
    dkpiClubSlides($this, $this->lazio);
    dkpiSet($this->season, $this->lazio, ['FANBASE_ITALY' => 1_800_000, 'SEASON_TICKETS' => 30_000]);
    $a = dkpiGroup($this->agent, [[$this->lazio->id => ['tier' => true, 'seconds' => 60]]]);

    $this->actingAs($this->admin)->post(route('proposals.groups.approve', $a->proposal_group_id))->assertSessionHasNoErrors();

    $snapshot = dkpiCurrent($a)->kpi_snapshot;

    expect($snapshot['version'])->toBe(2)
        ->and(array_keys($snapshot))->toBe(['version', 'campaign', 'clubs', 'network'])
        ->and($snapshot['campaign']['A']['total_matches'])->toBe(19)
        ->and(array_keys($snapshot['clubs']))->toBe([$this->lazio->id])
        ->and($snapshot['clubs'][$this->lazio->id]['competition_tier'])->toBe(1)
        ->and($snapshot['clubs'][$this->lazio->id]['kpis']['FANBASE_ITALY']['value'])->toBe(1_800_000)
        ->and($snapshot['clubs'][$this->lazio->id]['kpis']['SEASON_TICKETS']['source'])->toBe('Nielsen')
        ->and($snapshot['clubs'][$this->lazio->id]['missing'])->toHaveCount(11)
        ->and($snapshot['network'][ClubKpiSet::ALL_CLUBS]['FANBASE_ITALY']['sum'])->toBe(1_800_000)
        ->and($snapshot['network'][1]['FANBASE_ITALY']['sum'])->toBe(1_800_000);
});

it('M08-CA7, D-111, D-493: updating a KPI leaves the generated documents as they were; the regeneration takes the new value', function () {
    dkpiClubSlides($this, $this->lazio);
    dkpiSet($this->season, $this->lazio, ['FANBASE_ITALY' => 1_800_000]);
    $a = dkpiGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);
    $this->actingAs($this->admin)->post(route('proposals.groups.approve', $a->proposal_group_id))->assertSessionHasNoErrors();
    $old = dkpiCurrent($a);
    $before = $old->kpi_snapshot;

    $this->travel(1)->days();
    dkpiSet($this->season, $this->lazio, ['FANBASE_ITALY' => 1_900_000, 'TIKTOK_FOLLOWERS' => 400_000]);
    // A day later the session of the test has expired for inactivity (EnforceIdleTimeout).
    $this->flushSession();

    expect($old->refresh()->kpi_snapshot)->toBe($before)
        ->and($old->kpi_snapshot['clubs'][$this->lazio->id]['kpis']['FANBASE_ITALY']['value'])->toBe(1_800_000)
        ->and($old->kpi_snapshot['clubs'][$this->lazio->id]['kpis'])->not->toHaveKey('TIKTOK_FOLLOWERS');

    $this->actingAs($this->admin)->post(route('documents.regenerate', $a->proposal_group_id))->assertSessionHasNoErrors();

    $new = dkpiCurrent($a);

    expect($new->id)->not->toBe($old->id)
        ->and($new->status)->toBe(DocumentStatus::Ready)
        ->and($new->kpi_snapshot['clubs'][$this->lazio->id]['kpis']['FANBASE_ITALY']['value'])->toBe(1_900_000)
        ->and($new->kpi_snapshot['clubs'][$this->lazio->id]['kpis']['FANBASE_ITALY']['updated_at'])->toBe('2027-05-25T08:00:00+00:00')
        ->and($new->kpi_snapshot['clubs'][$this->lazio->id]['kpis']['TIKTOK_FOLLOWERS']['value'])->toBe(400_000)
        ->and($old->refresh()->kpi_snapshot)->toBe($before);
});

it('D-414, D-500: after the choice of the client the regenerated snapshot has only the club and the variant chosen', function () {
    dkpiNetworkSlides($this);
    dkpiClubSlides($this, $this->roma);
    dkpiSet($this->season, $this->lazio, ['FANBASE_ITALY' => 1_800_000]);
    dkpiSet($this->season, $this->roma, ['FANBASE_ITALY' => 1_250_000]);
    $a = dkpiGroup($this->agent, [
        [$this->lazio->id => ['tier' => true]],
        [$this->roma->id => ['tier' => true]],
    ]);
    $this->actingAs($this->admin)->post(route('proposals.groups.approve', $a->proposal_group_id))->assertSessionHasNoErrors();

    $network = dkpiCurrent($a);
    expect($network->document_type)->toBe(DocumentType::Network)
        ->and(array_keys($network->kpi_snapshot['campaign']))->toBe(['A', 'B'])
        ->and(array_keys($network->kpi_snapshot['clubs']))->toBe([$this->lazio->id, $this->roma->id])
        ->and($network->kpi_snapshot['network'][ClubKpiSet::ALL_CLUBS]['FANBASE_ITALY']['sum'])->toBe(3_050_000);

    $b = Proposal::query()->where('proposal_group_id', $a->proposal_group_id)->where('variant_letter', 'B')->sole();
    $this->actingAs($this->admin)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $b->id])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('documents.regenerate', $a->proposal_group_id))->assertSessionHasNoErrors();

    $club = dkpiCurrent($a);

    expect($club->document_type)->toBe(DocumentType::Club)
        ->and(array_keys($club->kpi_snapshot['campaign']))->toBe(['B'])
        ->and(array_keys($club->kpi_snapshot['clubs']))->toBe([$this->roma->id])
        ->and($club->kpi_snapshot['network'][ClubKpiSet::ALL_CLUBS]['FANBASE_ITALY']['sum'])->toBe(1_250_000);
});

it('D-502: the club document asks for all the 13 KPI, the network document for the 8 aggregated ones', function () {
    $clubOnly = array_fill_keys(DKPI_CLUB_ONLY, 1_000);
    dkpiSet($this->season, $this->lazio, $clubOnly);
    dkpiSet($this->season, $this->roma, array_fill_keys(array_slice(DKPI_AGGREGATED, 0, 7), 2_000));

    $club = dkpiCompose($this->season, [$this->lazio->id => 1]);
    $network = dkpiCompose($this->season, [$this->lazio->id => 1, $this->roma->id => 1]);

    expect($club->documentType)->toBe(DocumentType::Club)
        ->and($club->kpiWarnings())->toBe(['SS Lazio: mancano 8 KPI su 13 ('.dkpiNames(DKPI_AGGREGATED).')'])
        ->and($network->documentType)->toBe(DocumentType::Network)
        ->and($network->kpiWarnings())->toBe([
            'SS Lazio: mancano 8 KPI su 8 ('.dkpiNames(DKPI_AGGREGATED).')',
            'AS Roma: manca 1 KPI su 8 (Follower TikTok)',
        ])
        // The KPI are never warnings of the composition: the note of the agent does not change (D-503).
        ->and(array_filter($network->warnings, fn (string $warning): bool => str_contains($warning, 'KPI')))->toBe([]);

    dkpiSet($this->season, $this->roma, ['TIKTOK_FOLLOWERS' => 3_000]);
    dkpiAll($this->season, $this->lazio, 10_000);

    expect(dkpiCompose($this->season, [$this->lazio->id => 1, $this->roma->id => 1])->missingKpis)->toBe([])
        ->and(dkpiCompose($this->season, [$this->lazio->id => 1])->missingKpis)->toBe([]);
});

it('D-502: a KPI not updated for more than 6 months gives no warning in the composition', function () {
    dkpiAll($this->season, $this->lazio, 10_000);
    $this->travel(7)->months();

    $composition = dkpiCompose($this->season, [$this->lazio->id => 1]);

    expect($composition->kpis?->clubs[$this->lazio->id]->kpis['FANBASE_ITALY'])->not->toBeNull()
        ->and($composition->missingKpis)->toBe([]);
});

it('D-503: admin and manager see the clubs without KPI in the detail of the group and in the step "Documento", the agent does not', function () {
    dkpiClubSlides($this, $this->lazio);
    dkpiSet($this->season, $this->lazio, array_fill_keys(DKPI_AGGREGATED, 5_000));
    $a = dkpiGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);
    $warning = 'SS Lazio: mancano 5 KPI su 13 ('.dkpiNames(DKPI_CLUB_ONLY).')';

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.documentKpiWarnings', [$warning])
            ->where('group.documentWarnings', [])
            ->where('documentSlides.kpiWarnings', [$warning])
            ->where('documentSlides.warnings', []));

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.documentKpiWarnings', [])
            ->where('documentSlides.kpiWarnings', [])
            ->where('documentSlides.note', null));
});

it('D-503: the simulator lists the clubs without KPI apart from the warnings of the composition', function () {
    dkpiClubSlides($this, $this->lazio);
    dkpiSet($this->season, $this->lazio, array_fill_keys([...DKPI_AGGREGATED, 'AVERAGE_MATCH_FANS', 'DEDICATED_BROADCASTS', 'SEASON_TICKETS', 'DIGITAL_USERS'], 7_000));
    $this->actingAs($this->manager)->post(route('season.select'), ['season_id' => $this->season->id]);

    $query = http_build_query(['club' => [['id' => (string) $this->lazio->id, 'pacchetto' => 'TOP']]]);

    $this->actingAs($this->manager)->get(PortalPages::url('content_simulator').'?'.$query)
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.kpiWarnings', ['SS Lazio: manca 1 KPI su 13 (Contatti DEM)'])
            ->where('result.warnings', []));
});

it('D-503: the document is generated anyway and keeps the clubs without KPI in its page model', function () {
    dkpiClubSlides($this, $this->lazio);
    $a = dkpiGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);

    $this->actingAs($this->admin)->post(route('proposals.groups.approve', $a->proposal_group_id))->assertSessionHasNoErrors();

    $document = dkpiCurrent($a);

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->page_model['warnings'])->toBe([])
        ->and($document->page_model['kpi_warnings'])->toHaveCount(1)
        ->and($document->page_model['kpi_warnings'][0])->toStartWith('SS Lazio: mancano 13 KPI su 13 (Fanbase Italia, ')
        ->and($document->kpi_snapshot['clubs'][$this->lazio->id]['kpis'])->toBe([])
        ->and($document->kpi_snapshot['network'][ClubKpiSet::ALL_CLUBS]['FANBASE_ITALY'])->toBe(['name' => 'Fanbase Italia', 'sum' => 0, 'clubs' => [], 'missing' => [$this->lazio->id]]);
});

it('M08-CA6: the dynamic slide "Recap economico club" shows list price, discount and final total of the approved proposal', function () {
    dkpiClubSlides($this, $this->lazio);
    $a = dkpiGroup($this->agent, [[$this->lazio->id => ['tier' => true, 'seconds' => 60]]]);

    $this->actingAs($this->admin)
        ->post(route('proposals.groups.approve', $a->proposal_group_id), ['discounts' => [$a->id => ['type' => 'percent', 'value' => '15']]])
        ->assertSessionHasNoErrors();

    $recap = dkpiRenderer()->rendered[0]['pages'][1];
    /** @var list<RecapRow> $rows */
    $rows = $recap->data['rows'];
    $totals = $recap->data['totals'];

    // 155,680 € + 1′ × 19 matches × 1,000 € = 174,680 € at list price, less 15% = 148,478 €.
    expect($recap->layout)->toBe(DocumentLayout::ClubRecap)
        ->and(array_map(fn (RecapRow $row): string => $row->label, $rows))->toBe(['Top Partner', 'LED'])
        ->and($rows[0]->listPrice)->toBe('155680.00')
        ->and($rows[1]->listPrice)->toBe('19000.00')
        ->and($totals->listTotal)->toBe('174680.00')
        ->and($totals->hasDiscount())->toBeTrue()
        ->and($totals->discountLabel)->not->toBeNull()
        ->and($totals->finalTotal)->toBe('148478.00');
});
