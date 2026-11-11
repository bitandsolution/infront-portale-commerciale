<?php

use App\Enums\AuditAction;
use App\Enums\DocumentLayout;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideScope;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\Slide;
use App\Models\SlideSection;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Slide library (M08 F-01…F-03, §9; D-109, D-115, D-116, D-202, D-402…D-406; slice DM-02): page, new
| slide and change of a slide, audit, closed season, database constraints. Permissions of every route
| are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->seed(SystemDataSeeder::class);
    $this->season = Season::factory()->create();
});

function slidesPng(int $width = 1920, int $height = 1080): UploadedFile
{
    return UploadedFile::fake()->createWithContent('slide.png', SampleFiles::png($width, $height));
}

function slidesSection(string $code): int
{
    return (int) SlideSection::query()->where('code', $code)->value('id');
}

/**
 * An Infront club enrolled in a competition of the season (PriceListGrid, D-341).
 */
function slidesClub(Season $season): Club
{
    $club = Club::factory()->infront()->create();
    ClubEnrollment::query()->create(['competition_season_id' => CompetitionSeason::factory()->for($season)->create()->id, 'club_id' => $club->id]);

    return $club;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function slidesForm(array $overrides = []): array
{
    return array_merge([
        'title' => 'Benefit Top Partner',
        'scope' => 'infront',
        'club_id' => '',
        'document_type' => 'both',
        'slide_section_id' => (string) slidesSection('BENEFIT'),
        'sort_order' => '20',
        'layout' => 'static',
        'is_active' => '1',
        'image' => slidesPng(),
        'conditions' => ['partnership_tier' => ['TOP', 'PREMIUM'], 'has_led' => ['true']],
    ], $overrides);
}

it('D-406: the seeder creates the seven fixed sections in the order of the documents', function () {
    expect(SlideSection::query()->orderBy('sort_order')->pluck('code')->all())
        ->toBe(['INTRO', 'CLUB', 'PARTNERSHIP', 'BENEFIT', 'BRAND_EXPOSURE', 'RECAP', 'CLOSING']);

    $this->seed(SystemDataSeeder::class);
    expect(SlideSection::query()->count())->toBe(7);
});

it('F-01: admin and manager see the slides of the season of the bar, by section, with the conditions in words', function (string $role) {
    $lazio = slidesClub($this->season);
    Slide::factory()->forSeason($this->season)->section('CLOSING')->create(['title' => 'Grazie']);
    Slide::factory()->forSeason($this->season)->forClub($lazio)->document(SlideDocumentType::Club)->section('BENEFIT')
        ->withCondition(SlideConditionType::PartnershipTier, 'TOP', 'PREMIUM')->withCondition(SlideConditionType::HasLed, 'true')
        ->create(['title' => 'Benefit']);
    Slide::factory()->forSeason($this->season)->layout(DocumentLayout::LedMockup)->section('BRAND_EXPOSURE')->create(['title' => 'Simulazione']);
    Slide::factory()->create(['title' => 'Altra stagione']);

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(PortalPages::url('content_slides'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Content/Slides/Index')
            ->where('season.code', $this->season->code)
            ->has('slides', 3)
            ->where('slides.0.title', 'Benefit')
            ->where('slides.0.clubName', $lazio->short_name)
            ->where('slides.0.kindLabel', 'Statica')
            ->where('slides.0.conditions', ['Livello partnership = Premium o Top', 'LED presente = Sì'])
            ->where('slides.1.title', 'Simulazione')
            ->where('slides.1.kindLabel', 'Dinamica · Simulazione LED')
            ->where('slides.1.imageUrl', null)
            ->where('slides.2.title', 'Grazie')
            ->where('slides.2.conditions', [])
            ->where('createUrl', route('content.slides.create')));
})->with(['admin', 'manager']);

it('M08 §10: two active slides with the same section and order are flagged', function () {
    Slide::factory()->forSeason($this->season)->section('BENEFIT', 10)->count(2)->create();
    Slide::factory()->forSeason($this->season)->section('BENEFIT', 20)->create();

    $this->actingAs(User::factory()->manager()->create())
        ->get(PortalPages::url('content_slides'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('slides.0.duplicateOrder', true)
            ->where('slides.1.duplicateOrder', true)
            ->where('slides.2.duplicateOrder', false));
});

it('F-01, F-02, D-402: the manager uploads a slide with its conditions, and the audit records it', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->post(route('content.slides.store'), slidesForm())
        ->assertRedirect(PortalPages::url('content_slides'))
        ->assertSessionHas('toast.message', 'Slide Benefit Top Partner caricata');

    $slide = Slide::query()->with('conditions')->sole();
    expect($slide->season_id)->toBe($this->season->id)
        ->and($slide->scope)->toBe(SlideScope::Infront)
        ->and($slide->image_file_id)->not->toBeNull()
        ->and($slide->created_by)->toBe($manager->id)
        ->and($slide->conditionValues())->toBe(['partnership_tier' => ['PREMIUM', 'TOP'], 'has_led' => ['true']]);

    $event = soleAuditEvent(AuditAction::SlideCreated);
    expect($event->area)->toBe('content')
        ->and($event->object_label)->toBe('Benefit Top Partner ('.$this->season->code.')')
        ->and($event->new_values)->toMatchArray([
            'title' => 'Benefit Top Partner',
            'scope' => 'infront',
            'section_code' => 'BENEFIT',
            'kind' => 'static',
            'conditions' => 'partnership_tier: PREMIUM, TOP; has_led: true',
        ]);
});

it('M08-CA8, D-112: a PNG 1600×900 is refused', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('content.slides.store'), slidesForm(['image' => slidesPng(1600, 900)]))
        ->assertSessionHasErrors('image');

    expect(Slide::query()->count())->toBe(0);
});

it('D-405: the image is required, except for the LED simulation, which never keeps one', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('content.slides.store'), slidesForm(['image' => null]))
        ->assertSessionHasErrors(['image' => 'Carica l\'immagine della slide (PNG 1920×1080).']);

    $this->actingAs($admin)
        ->post(route('content.slides.store'), slidesForm(['layout' => 'led_mockup', 'title' => 'Simulazione LED']))
        ->assertSessionHasNoErrors();

    expect(Slide::query()->sole())
        ->layout_code->toBe(DocumentLayout::LedMockup)
        ->image_file_id->toBeNull();
});

it('D-405: the network recap is an Infront slide of the network document, the club recap a club slide of the club document', function () {
    $admin = User::factory()->admin()->create();
    $club = slidesClub($this->season);

    $this->actingAs($admin)
        ->post(route('content.slides.store'), slidesForm(['layout' => 'network_recap', 'document_type' => 'both']))
        ->assertSessionHasErrors(['layout' => 'Il recap network è una slide Infront del documento network.']);

    $this->actingAs($admin)
        ->post(route('content.slides.store'), slidesForm(['layout' => 'club_recap', 'document_type' => 'club']))
        ->assertSessionHasErrors(['layout' => 'Il recap club è una slide del club per il documento club.']);

    $this->actingAs($admin)
        ->post(route('content.slides.store'), slidesForm(['layout' => 'club_recap', 'document_type' => 'club', 'scope' => 'club', 'club_id' => (string) $club->id]))
        ->assertSessionHasNoErrors();
});

it('D-341: the club of a slide is an Infront club enrolled in the season', function () {
    $other = Club::factory()->infront()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('content.slides.store'), slidesForm(['scope' => 'club', 'club_id' => (string) $other->id]))
        ->assertSessionHasErrors(['club_id' => 'Il club non è un club Infront iscritto a una competizione della stagione.']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('content.slides.store'), slidesForm(['scope' => 'club', 'club_id' => '']))
        ->assertSessionHasErrors(['club_id' => 'Scegli il club della slide.']);
});

it('D-202: the conditions accept only stable codes, and Sì or No for the yes / no ones', function (array $conditions, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('content.slides.store'), slidesForm(['conditions' => $conditions]))
        ->assertSessionHasErrors($field);
})->with([
    'unknown tier' => [['partnership_tier' => ['GOLD']], 'conditions.partnership_tier'],
    'unknown format' => [['led_format' => ['MAXI']], 'conditions.led_format'],
    'yes and no together' => [['has_hospitality' => ['true', 'false']], 'conditions.has_hospitality'],
    'unknown competition' => [['competition_tier' => ['7']], 'conditions.competition_tier'],
    'unknown condition' => [['sponsor' => ['true']], 'conditions'],
]);

it('D-406, C10: the change of a slide writes only the changed fields; a new image is a new file', function () {
    $admin = User::factory()->admin()->create();
    $slide = Slide::factory()->forSeason($this->season)->section('BENEFIT', 20)
        ->withCondition(SlideConditionType::PartnershipTier, 'TOP')->create(['title' => 'Benefit']);
    $oldImage = $slide->image_file_id;

    $this->actingAs($admin)
        ->put(route('content.slides.update', $slide), slidesForm([
            'title' => 'Benefit',
            'image' => null,
            'is_active' => '0',
            'conditions' => ['partnership_tier' => ['TOP']],
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Slide Benefit salvata');

    $event = soleAuditEvent(AuditAction::SlideUpdated);
    expect($event->old_values)->toBe(['is_active' => true])
        ->and($event->new_values)->toBe(['is_active' => false])
        ->and($slide->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->put(route('content.slides.update', $slide), slidesForm(['title' => 'Benefit', 'is_active' => '0', 'conditions' => []]))
        ->assertSessionHasNoErrors();

    $slide->refresh();
    $last = AuditEvent::query()->where('action', AuditAction::SlideUpdated->value)->latest('id')->firstOrFail();
    expect($slide->image_file_id)->not->toBe($oldImage)
        ->and(StoredFile::query()->whereKey($oldImage)->exists())->toBeTrue()
        ->and($slide->conditions()->count())->toBe(0)
        ->and(array_keys($last->new_values ?? []))->toBe(['image_file_id', 'conditions']);
});

it('nothing is written when the slide does not change', function () {
    $slide = Slide::factory()->forSeason($this->season)->section('BENEFIT', 20)->create(['title' => 'Benefit']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('content.slides.update', $slide), slidesForm(['title' => 'Benefit', 'image' => null, 'conditions' => []]))
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');

    expect(AuditEvent::query()->where('action', AuditAction::SlideUpdated->value)->count())->toBe(0);
});

it('D-405: becoming a LED simulation drops the image of the slide', function () {
    $slide = Slide::factory()->forSeason($this->season)->create(['title' => 'Simulazione']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('content.slides.update', $slide), slidesForm(['title' => 'Simulazione', 'layout' => 'led_mockup', 'image' => null, 'conditions' => []]))
        ->assertSessionHasNoErrors();

    expect($slide->refresh()->image_file_id)->toBeNull()
        ->and($slide->kind->value)->toBe('dynamic');
});

it('D-317: the library of a closed season is read only', function () {
    $closed = Season::factory()->closed()->create();
    $slide = Slide::factory()->forSeason($closed)->create(['title' => 'Vecchia']);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('season.select'), ['season_id' => $closed->id]);

    $this->actingAs($admin)
        ->get(PortalPages::url('content_slides'))
        ->assertInertia(fn (Assert $page) => $page->where('season.closed', true)->where('createUrl', null));

    $this->actingAs($admin)
        ->from(PortalPages::url('content_slides'))
        ->put(route('content.slides.update', $slide), slidesForm(['title' => 'Nuova', 'image' => null]))
        ->assertRedirect(PortalPages::url('content_slides'))
        ->assertSessionHas('toast.message', 'La stagione '.$closed->code.' è chiusa: la libreria slide è in sola lettura');

    expect($slide->refresh()->title)->toBe('Vecchia');
});

it('D-406: the slide image is downloaded by admin and manager', function () {
    $slide = Slide::factory()->forSeason($this->season)->create();

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('files.download', $slide->image_file_id))
        ->assertOk();
});

it('C9, D-405: the database refuses a club slide without club, a static slide with a layout and a missing image', function (array $attributes) {
    $slide = Slide::factory()->forSeason($this->season)->create();

    expect(fn () => DB::table('slides')->where('id', $slide->id)->update($attributes))
        ->toThrow(QueryException::class);
})->with([
    'club without club' => [['scope' => 'club', 'club_id' => null]],
    'static with layout' => [['layout_code' => 'cover']],
    'dynamic without image' => [['kind' => 'dynamic', 'layout_code' => 'cover', 'image_file_id' => null]],
    'unknown document type' => [['document_type' => 'landing']],
]);

it('C3: the conditions disappear with their slide', function () {
    $slide = Slide::factory()->forSeason($this->season)->withCondition(SlideConditionType::HasLed, 'true')->create();

    DB::table('slides')->where('id', $slide->id)->delete();

    expect(DB::table('slide_conditions')->count())->toBe(0);
});
