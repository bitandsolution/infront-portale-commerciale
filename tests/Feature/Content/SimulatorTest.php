<?php

use App\Enums\DocumentLayout;
use App\Enums\FilePurpose;
use App\Enums\LedPhotoKind;
use App\Enums\SeasonStatus;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\LedMockupSetting;
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Documents\SampleDeck;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Simulator of the composition of a document (M08 F-04, FL-01, CA5; D-118, D-404, D-494…D-499; slice
| F6-02): "Contenuti › Simulatore" composes a proposal built from the choices in the address with
| DocumentComposer, shows the slides in order with pages and warnings and the slides excluded by a
| condition; nothing is saved. Permissions are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->seed(SystemDataSeeder::class);
    $this->travelTo(Carbon::parse('2027-03-01 10:00:00'));
    $this->season = Season::factory()->create();
    $this->manager = User::factory()->manager()->create();
    $this->lazio = simClub($this->season, 'SS Lazio', 1);
});

/**
 * An Infront club enrolled in a championship of the season with the given tier (1 = Serie A).
 */
function simClub(Season $season, string $name, int $tier): Club
{
    $club = Club::factory()->infront()->create(['name' => $name]);
    $edition = CompetitionSeason::factory()->for($season)->create();
    $edition->competition->update(['tier' => $tier, 'name' => $tier === 1 ? 'Serie A' : 'Serie B']);
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);

    return $club;
}

/**
 * The slides of the document of the Lazio: cover, benefit Top / Premium / Official, LED, hospitality,
 * recap and closing.
 */
function simLazioLibrary(Season $season, Club $lazio): void
{
    $slide = fn () => Slide::factory()->forSeason($season)->forClub($lazio)->document(SlideDocumentType::Club);
    $slide()->layout(DocumentLayout::Cover)->section('INTRO', 1)->create(['title' => 'Copertina']);
    $slide()->section('BENEFIT', 10)->withCondition(SlideConditionType::PartnershipTier, 'TOP')->create(['title' => 'Benefit Top']);
    $slide()->section('BENEFIT', 20)->withCondition(SlideConditionType::PartnershipTier, 'PREMIUM')->create(['title' => 'Benefit Premium']);
    $slide()->section('BENEFIT', 30)->withCondition(SlideConditionType::PartnershipTier, 'OFFICIAL')->create(['title' => 'Benefit Official']);
    $slide()->section('BRAND_EXPOSURE', 10)->withCondition(SlideConditionType::LedFormat, 'DOMINATION')->create(['title' => 'Domination']);
    $slide()->section('BRAND_EXPOSURE', 20)->withCondition(SlideConditionType::HasHospitality, 'true')->create(['title' => 'Ticketing & Hospitality']);
    $slide()->layout(DocumentLayout::ClubRecap)->section('RECAP')->create(['title' => 'Recap']);
    $slide()->section('CLOSING')->create(['title' => 'Grazie']);
}

/**
 * @param  list<array{0: Club, 1?: string|null, 2?: string|null, 3?: bool}>  $clubs  club, package, format, hospitality
 */
function simGet(User $user, array $clubs, ?string $mode = null): TestResponse
{
    $query = [];

    foreach ($clubs as $index => $club) {
        $query['club'][$index] = array_filter([
            'id' => (string) $club[0]->id,
            'pacchetto' => $club[1] ?? null,
            'format' => $club[2] ?? null,
            'hospitality' => ($club[3] ?? false) ? '1' : null,
        ], fn (?string $value): bool => $value !== null);
    }

    if ($mode !== null) {
        $query['modalita'] = $mode;
    }

    return test()->actingAs($user)->get(PortalPages::url('content_simulator').($query === [] ? '' : '?'.http_build_query($query)));
}

/**
 * The props of the page.
 *
 * @return array<string, mixed>
 */
function simProps(TestResponse $response): array
{
    $props = [];
    $response->assertOk()->assertInertia(function (Assert $page) use (&$props): void {
        $page->component('Content/Simulator');
        $props = $page->toArray()['props'];
    });

    return $props;
}

/**
 * Titles of the slides of the result, in order, with whether they enter the document.
 *
 * @param  array<string, mixed>  $result
 * @return array<string, bool>
 */
function simSlides(array $result): array
{
    $slides = [];

    foreach ($result['sections'] as $section) {
        foreach ($section['slides'] as $slide) {
            $slides[$slide['title']] = $slide['included'];
        }
    }

    return $slides;
}

it('M08-CA5, FL-01: for "Lazio, Top, Domination, hospitality sì" it shows the ordered slides of the document', function () {
    simLazioLibrary($this->season, $this->lazio);

    $props = simProps(simGet($this->manager, [[$this->lazio, 'TOP', 'DOMINATION', true]]));
    $result = $props['result'];

    expect($result['documentLabel'])->toBe('Documento club')
        ->and(simSlides($result))->toBe([
            'Copertina' => true,
            'Benefit Top' => true,
            'Domination' => true,
            'Ticketing & Hospitality' => true,
            'Recap' => true,
            'Grazie' => true,
        ])
        ->and(array_column($result['sections'], 'name'))->toBe(['Introduzione', 'Benefit', 'Brand exposure', 'Recap', 'Chiusura'])
        ->and($result['includedSlides'])->toBe(6)
        ->and($result['totalSlides'])->toBe(6)
        ->and($result['pages'])->toBe(6)
        ->and($result['warnings'])->toBe([])
        ->and($result['sections'][1]['slides'][0]['conditions'])->toBe(['Livello partnership = Top'])
        ->and($result['sections'][0]['slides'][0]['conditions'])->toBe([])
        ->and($result['sections'][0]['slides'][0]['mandatory'])->toBeTrue()
        ->and($props['choice'])->toBe(['clubs' => [['id' => $this->lazio->id, 'tier' => 'TOP', 'format' => 'DOMINATION', 'hospitality' => true]], 'mode' => 'complete']);
});

it('D-495: the slides excluded by a condition are listed with the condition not satisfied', function () {
    simLazioLibrary($this->season, $this->lazio);

    $result = simProps(simGet($this->manager, [[$this->lazio, 'PREMIUM']]))['result'];

    expect(array_keys(simSlides($result)))->toBe(['Copertina', 'Benefit Premium', 'Recap', 'Grazie'])
        ->and(array_map(fn (array $slide): array => [$slide['title'], $slide['section'], $slide['reasons']], $result['excluded']))->toBe([
            ['Benefit Top', 'Benefit', ['Richiede: Livello partnership = Top']],
            ['Benefit Official', 'Benefit', ['Richiede: Livello partnership = Official']],
            ['Domination', 'Brand exposure', ['Richiede: Format LED = Domination']],
            ['Ticketing & Hospitality', 'Brand exposure', ['Richiede: Hospitality presente = Sì']],
        ]);
});

it('D-495: the LED simulation without any LED is excluded with its own reason', function () {
    Slide::factory()->forSeason($this->season)->forClub($this->lazio)->document(SlideDocumentType::Club)
        ->layout(DocumentLayout::LedMockup)->section('BRAND_EXPOSURE')->create(['title' => 'Simulazione LED']);

    $result = simProps(simGet($this->manager, [[$this->lazio, 'TOP']]))['result'];

    expect(simSlides($result))->toBe([])
        ->and($result['excluded'])->toHaveCount(1)
        ->and($result['excluded'][0]['title'])->toBe('Simulazione LED')
        ->and($result['excluded'][0]['reasons'])->toBe(['Nessun club della proposta ha il LED'])
        ->and($result['excluded'][0]['thumbnailUrl'])->toBeNull();
});

it('D-499: a LED simulation without configuration makes no page; with the generic one it makes its page', function () {
    Slide::factory()->forSeason($this->season)->forClub($this->lazio)->document(SlideDocumentType::Club)
        ->layout(DocumentLayout::LedMockup)->section('BRAND_EXPOSURE')->create(['title' => 'Simulazione LED']);

    $without = simProps(simGet($this->manager, [[$this->lazio, null, 'FILA_1']]))['result'];

    expect($without['pages'])->toBe(0)
        ->and($without['sections'][0]['slides'][0]['pagesLabel'])->toBe('Nessuna pagina: simulazione LED non configurata')
        ->and($without['warnings'])->toContain('Simulazione LED non configurata per SS Lazio (1ª fila): la pagina non entra nel documento');

    LedMockupSetting::query()->create([
        'club_id' => null,
        'photo_kind' => LedPhotoKind::Row1,
        'photo_file_id' => storeTestFile(FilePurpose::FieldPhoto, null, SampleDeck::fieldPhoto(), 'campo.png')->id,
        'areas' => [[[0, 224], [1768, 224], [1768, 262], [0, 262]]],
        'logo_height_percent' => 85,
        'spacing' => 30,
    ]);

    $with = simProps(simGet($this->manager, [[$this->lazio, null, 'FILA_1']]))['result'];

    expect($with['pages'])->toBe(1)
        ->and($with['sections'][0]['slides'][0]['pagesLabel'])->toBe('1 pagina, per il club con il LED');
});

it('M08-CA4, D-118: two clubs make a network document of Infront slides, with the competition from the enrollment', function () {
    $empoli = simClub($this->season, 'Empoli FC', 2);
    $network = fn () => Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Network)->section('CLUB');
    $network()->withCondition(SlideConditionType::CompetitionTier, '1')->create(['title' => 'KPI Serie A']);
    $network()->withCondition(SlideConditionType::CompetitionTier, '2')->create(['title' => 'KPI Serie B']);
    $network()->withCondition(SlideConditionType::PartnershipTier, 'TOP')->create(['title' => 'Benefit Top']);
    Slide::factory()->forSeason($this->season)->forClub($this->lazio)->document(SlideDocumentType::Club)->create(['title' => 'Slide della Lazio']);

    $both = simProps(simGet($this->manager, [[$this->lazio, 'PREMIUM'], [$empoli, 'TOP']]))['result'];
    $onlyA = simProps(simGet($this->manager, [[$this->lazio, 'TOP']]))['result'];

    expect($both['documentLabel'])->toBe('Documento network')
        ->and(array_keys(simSlides($both)))->toBe(['KPI Serie A', 'KPI Serie B', 'Benefit Top'])
        ->and($both['excluded'])->toBe([])
        ->and($onlyA['documentLabel'])->toBe('Documento club')
        ->and(array_keys(simSlides($onlyA)))->toBe(['Slide della Lazio']);
});

it('D-397: in "Solo riepilogo" only cover, recap and closing enter, the others are shown out of it', function () {
    simLazioLibrary($this->season, $this->lazio);

    $result = simProps(simGet($this->manager, [[$this->lazio, 'TOP', 'DOMINATION', true]], 'summary'))['result'];

    expect(simSlides($result))->toBe([
        'Copertina' => true,
        'Benefit Top' => false,
        'Domination' => false,
        'Ticketing & Hospitality' => false,
        'Recap' => true,
        'Grazie' => true,
    ])
        ->and($result['includedSlides'])->toBe(3)
        ->and($result['pages'])->toBe(3);
});

it('D-496: the options are the Infront clubs of the season with their championship and every active package and format', function () {
    Club::factory()->infront()->create(['name' => 'Non iscritto']);

    $options = simProps(simGet($this->manager, []))['options'];

    expect($options['clubs'])->toBe([['id' => $this->lazio->id, 'name' => 'SS Lazio', 'competition' => 'Serie A']])
        ->and(array_column($options['tiers'], 'code'))->toBe(['LIBERA', 'TOP', 'PREMIUM', 'OFFICIAL', 'OFFICIAL_2'])
        ->and(array_column($options['formats'], 'code'))->toBe(['JUMBO', 'DOMINATION', 'FILA_1', 'FILA_2'])
        ->and(array_column($options['modes'], 'value'))->toBe(['complete', 'summary']);
});

it('D-498: without clubs there is no result; unknown clubs, codes and repeated clubs in the address are ignored', function () {
    simLazioLibrary($this->season, $this->lazio);
    $other = Club::factory()->infront()->create();

    expect(simProps(simGet($this->manager, []))['result'])->toBeNull();

    $props = simProps(simGet($this->manager, [[$other, 'TOP'], [$this->lazio, 'NON_ESISTE', 'jumbo'], [$this->lazio, 'TOP']], 'sconosciuta'));

    expect($props['choice'])->toBe(['clubs' => [['id' => $this->lazio->id, 'tier' => null, 'format' => null, 'hospitality' => false]], 'mode' => 'complete'])
        ->and(array_keys(simSlides($props['result'])))->toBe(['Copertina', 'Recap', 'Grazie']);
});

it('D-494: the Libera has no partnership: "Partnership presente = Sì" is excluded', function () {
    $slide = fn () => Slide::factory()->forSeason($this->season)->forClub($this->lazio)->document(SlideDocumentType::Club);
    $slide()->withCondition(SlideConditionType::HasPartnership, 'true')->create(['title' => 'IP rights']);
    $slide()->withCondition(SlideConditionType::HasPartnership, 'false')->create(['title' => 'Senza partnership']);

    $result = simProps(simGet($this->manager, [[$this->lazio, 'LIBERA']]))['result'];

    expect(array_keys(simSlides($result)))->toBe(['Senza partnership'])
        ->and($result['excluded'][0]['reasons'])->toBe(['Richiede: Partnership presente = Sì']);
});

it('D-494: nothing is saved and a closed season can be simulated', function () {
    simLazioLibrary($this->season, $this->lazio);
    $this->season->update(['status' => SeasonStatus::Closed]);
    $events = AuditEvent::query()->count();
    $slides = Slide::query()->count();

    $props = simProps(simGet(User::factory()->admin()->create(), [[$this->lazio, 'TOP']]));

    expect($props['season']['closed'])->toBeTrue()
        ->and($props['result']['includedSlides'])->toBe(4)
        ->and(AuditEvent::query()->count())->toBe($events)
        ->and(Slide::query()->count())->toBe($slides);
});
