<?php

use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Data\LayoutPage;
use App\Domain\Documents\Layouts\LedMockupLayout;
use App\Domain\Files\Services\FileDerivatives;
use App\Enums\AuditAction;
use App\Enums\DocumentLayout;
use App\Enums\DocumentMode;
use App\Enums\FileDerivativeKind;
use App\Enums\FilePurpose;
use App\Enums\LedPhotoKind;
use App\Enums\SlideDocumentType;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\Document;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedMockupSetting;
use App\Models\LedPrice;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\StoredFileDerivative;
use App\Models\User;
use App\Support\Documents\SampleDeck;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakePdfRenderer;

/*
| Step "Documento" of the configurator (D-397, D-418…D-420; slice DM, PR DM-05): the slides of the
| document of a group, chosen by the agent on drafts and rejected groups and by admin and manager
| while the group waits, saved on the group (mode and excluded slides), frozen on the versions at the
| approval and used by the PDF; the thumbnails of the slide images (stored_file_derivatives). The
| routes are in the route × role matrix (RouteMatrixTest).
|
| Club document of SS Lazio: cover, "Benefit Lazio", the LED simulation (one page per club with the
| LED), recap and closing "Contatti Lazio".
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->lazio = dslClub($this, 'SS Lazio', 'LAZIO');

    $this->cover = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('INTRO')->layout(DocumentLayout::Cover)->create(['title' => 'Copertina Lazio']);
    $this->benefit = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('BENEFIT')->create(['title' => 'Benefit Lazio']);
    $this->led = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('BRAND_EXPOSURE')->layout(DocumentLayout::LedMockup)->create(['title' => 'Simulazione LED']);
    $this->recap = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('RECAP')->layout(DocumentLayout::ClubRecap)->document(SlideDocumentType::Club)->create(['title' => 'Recap Lazio']);
    $this->closing = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('CLOSING')->create(['title' => 'Contatti Lazio']);

    $this->agent = User::factory()->agent()->create();
    $this->otherAgent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
});

/** A club with the tier TOP and the 2nd row, priced and available. */
function dslClub(object $test, string $name, string $code): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, 'code' => $code, 'short_name' => ucfirst(strtolower($code))]);
    ClubEnrollment::query()->create(['competition_season_id' => $test->league->id, 'club_id' => $club->id]);
    $tier = PartnershipTier::query()->where('code', 'TOP')->firstOrFail();
    $format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();
    $pool = InventoryPool::query()->where('code', 'FILA_2')->firstOrFail();

    LedPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'led_format_id' => $format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'inventory_pool_id' => $pool->id, 'is_enabled' => true, 'seconds_per_match' => 600]);
    PartnershipPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'partnership_tier_id' => $tier->id, 'season_price' => '155680.00']);
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => $tier->id, 'is_enabled' => true, 'quantity' => 2]);

    return $club;
}

/**
 * A draft of the agent for a new client, with the given clubs: club id → seconds of the 2nd row (the
 * tier TOP always). Without clubs the draft has none.
 *
 * @param  array<int, int>  $clubs
 */
function dslDraft(User $agent, array $clubs): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();

    if ($clubs === []) {
        return $proposal;
    }

    $version = $proposal->workingVersion;
    $rows = [];

    foreach ($clubs as $clubId => $seconds) {
        $rows[] = [
            'club_id' => $clubId,
            'partnership_tier_id' => PartnershipTier::query()->where('code', 'TOP')->value('id'),
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

    return $proposal->refresh();
}

/**
 * @param  list<int>  $excluded
 */
function dslSave(User $actor, Proposal $proposal, string $mode, array $excluded): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->put(route('proposals.groups.slides', $proposal->proposal_group_id), ['mode' => $mode, 'excluded_slide_ids' => $excluded]);
}

function dslGroup(Proposal $proposal): ProposalGroup
{
    return ProposalGroup::query()->findOrFail($proposal->proposal_group_id);
}

function dslSubmit(User $agent, Proposal $proposal): void
{
    test()->actingAs($agent)->post(route('proposals.groups.submit', $proposal->proposal_group_id))->assertSessionHasNoErrors();
}

/**
 * The slides of the prop "documentSlides" of a page: title → [included, excluded, locked].
 *
 * @return array<string, array{bool, bool, bool}>
 */
function dslListed(TestResponse $response): array
{
    $listed = [];
    $response->assertInertia(function (Assert $page) use (&$listed): void {
        foreach ($page->toArray()['props']['documentSlides']['sections'] as $section) {
            foreach ($section['slides'] as $slide) {
                $listed[$slide['title']] = [$slide['included'], $slide['excluded'], $slide['locked']];
            }
        }
    });

    return $listed;
}

it('D-397: step 6 "Documento" lists the slides of the group by section, cover and recap ticked and locked', function () {
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);

    $response = $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Proposals/Configurator')
        ->where('step', 6)
        ->where('lastStep', 6)
        ->where('documentSlides.mode', 'complete')
        ->where('documentSlides.editable', true)
        ->where('documentSlides.frozen', false)
        ->where('documentSlides.documentLabel', 'Documento club')
        ->where('documentSlides.totalSlides', 5)
        ->where('documentSlides.includedSlides', 5)
        ->where('documentSlides.pages', 5)
        ->where('documentSlides.warnings', [])
        ->where('documentSlides.saveUrl', route('proposals.groups.slides', $proposal->proposal_group_id))
        ->has('documentSlides.sections', 5)
        ->where('documentSlides.sections.0.slides.0.kind', 'cover')
        ->where('documentSlides.sections.1.slides.0.thumbnailUrl', route('files.thumbnail', $this->benefit->image_file_id))
        ->where('documentSlides.sections.2.slides.0.kind', 'led')
        ->where('documentSlides.sections.2.slides.0.thumbnailUrl', null)
        ->where('documentSlides.sections.2.slides.0.pagesLabel', '1 pagina, per il club con il LED'));

    expect(dslListed($response))->toBe([
        'Copertina Lazio' => [true, false, true],
        'Benefit Lazio' => [true, false, false],
        'Simulazione LED' => [true, false, false],
        'Recap Lazio' => [true, false, true],
        'Contatti Lazio' => [true, false, false],
    ]);
});

it('D-373: without clubs the step "Documento" is not open: the configurator shows step 4', function () {
    $proposal = dslDraft($this->agent, []);

    $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->assertInertia(fn (Assert $page) => $page->where('step', 4)->where('documentSlides.totalSlides', 0));
});

it('D-418: the agent excludes a slide; cover, recap and unknown ids are ignored; one audit event with the titles', function () {
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);

    dslSave($this->agent, $proposal, 'complete', [$this->benefit->id, $this->cover->id, $this->recap->id, 999999])
        ->assertRedirect(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->assertSessionHasNoErrors();

    $group = dslGroup($proposal);
    expect($group->document_mode)->toBe(DocumentMode::Complete)
        ->and($group->excluded_slide_ids)->toBe([$this->benefit->id]);

    $event = soleAuditEvent(AuditAction::ProposalDocumentSlidesUpdated);
    expect($event->object_id)->toBe($proposal->id)
        ->and($event->old_values)->toBe(['excluded_slides' => null])
        ->and($event->new_values)->toBe(['proposal_group_id' => $group->id, 'excluded_slides' => 'Benefit Lazio']);

    // The same choice again: nothing written, no event.
    dslSave($this->agent, $proposal, 'complete', [$this->benefit->id])->assertSessionHasNoErrors();
    expect(auditEventsOf(AuditAction::ProposalDocumentSlidesUpdated))->toHaveCount(1);

    $listed = dslListed($this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6])));
    expect($listed['Benefit Lazio'])->toBe([false, true, false]);
});

it('D-418: "Solo riepilogo" keeps cover, recap and closing; the exclusions come back with "Documento completo"', function () {
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);
    dslSave($this->agent, $proposal, 'complete', [$this->benefit->id])->assertSessionHasNoErrors();

    dslSave($this->agent, $proposal, 'summary', [$this->benefit->id])->assertSessionHasNoErrors();

    $response = $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]));
    $response->assertInertia(fn (Assert $page) => $page->where('documentSlides.mode', 'summary')->where('documentSlides.includedSlides', 3)->where('documentSlides.pages', 3));
    expect(dslListed($response))->toBe([
        'Copertina Lazio' => [true, false, true],
        'Benefit Lazio' => [false, true, true],
        'Simulazione LED' => [false, false, true],
        'Recap Lazio' => [true, false, true],
        'Contatti Lazio' => [true, false, false],
    ])->and(auditEventsOf(AuditAction::ProposalDocumentSlidesUpdated)->last()?->new_values)->toBe(['proposal_group_id' => dslGroup($proposal)->id, 'document_mode' => 'summary']);

    // A slide added afterwards to the library does not enter the summary.
    Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('PARTNERSHIP')->create(['title' => 'Partnership Lazio']);
    $listed = dslListed($this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6])));
    expect($listed['Partnership Lazio'])->toBe([false, false, true]);

    dslSave($this->agent, $proposal, 'complete', [$this->benefit->id])->assertSessionHasNoErrors();
    $listed = dslListed($this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6])));
    expect($listed['Benefit Lazio'])->toBe([false, true, false])
        ->and($listed['Partnership Lazio'])->toBe([true, false, false]);
});

it('D-419: the exclusion of a slide no longer in the composition stays saved', function () {
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);
    dslSave($this->agent, $proposal, 'complete', [$this->led->id])->assertSessionHasNoErrors();

    // Without the LED the simulation leaves the composition; another tick keeps its exclusion.
    $proposal->refresh();
    $version = $proposal->workingVersion;
    $this->actingAs($this->agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => [['club_id' => $this->lazio->id, 'partnership_tier_id' => PartnershipTier::query()->where('code', 'TOP')->value('id'), 'led_format_id' => null, 'led_seconds' => 0, 'seats' => []]],
        ])
        ->assertSessionHasNoErrors();

    dslSave($this->agent, $proposal, 'complete', [$this->benefit->id])->assertSessionHasNoErrors();

    expect(dslGroup($proposal)->excluded_slide_ids)->toBe([$this->benefit->id, $this->led->id]);
});

it('D-397: once sent, the agent no longer changes the slides; admin and manager do, only while the group waits', function () {
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);

    // A draft: admin and manager refused with the toast.
    dslSave($this->manager, $proposal, 'complete', [$this->benefit->id])
        ->assertSessionHas('toast.message', 'Le slide del documento si scelgono finché la proposta è in bozza o, per chi approva, in approvazione: ricarica la pagina');
    dslSave($this->otherAgent, $proposal, 'complete', [$this->benefit->id])->assertForbidden();

    dslSubmit($this->agent, $proposal);

    dslSave($this->agent, $proposal, 'complete', [$this->benefit->id])
        ->assertSessionHas('toast.message', 'Le slide del documento si scelgono finché la proposta è in bozza o, per chi approva, in approvazione: ricarica la pagina');
    dslSave($this->manager, $proposal, 'complete', [$this->benefit->id])->assertSessionHasNoErrors();

    expect(dslGroup($proposal)->excluded_slide_ids)->toBe([$this->benefit->id])
        ->and(soleAuditEvent(AuditAction::ProposalDocumentSlidesUpdated)->user_id)->toBe($this->manager->id);

    // The detail of the group: editable for the manager, read only for the agent.
    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->component('Proposals/Group')->where('documentSlides.editable', true)->where('documentSlides.includedSlides', 4));
    $this->actingAs($this->agent)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('documentSlides.editable', false));
});

it('D-419: the approval freezes the choice on the versions and the PDF leaves out the excluded slides', function () {
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);
    dslSave($this->agent, $proposal, 'complete', [$this->benefit->id])->assertSessionHasNoErrors();
    dslSubmit($this->agent, $proposal);

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id), ['discounts' => []])
        ->assertSessionHasNoErrors();

    $version = ProposalVersion::query()->findOrFail($proposal->refresh()->current_version_id);
    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();

    expect($version->document_mode)->toBe(DocumentMode::Complete)
        ->and($version->excluded_slide_ids)->toBe([$this->benefit->id])
        ->and($document->page_model['document_mode'])->toBe('complete')
        ->and($document->page_model['excluded_slide_ids'])->toBe([$this->benefit->id])
        // The LED simulation is skipped until DM-06 (page_model.skipped).
        ->and(array_column($document->page_model['slides'], 'title'))->toBe(['Copertina Lazio', 'Recap Lazio', 'Contatti Lazio']);

    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);
    expect($renderer->rendered[0]['pages'])->toHaveCount(3);

    // After the approval nobody changes the slides; the detail shows the frozen choice.
    dslSave($this->admin, $proposal, 'summary', [])
        ->assertSessionHas('toast.message', 'Le slide del documento si scelgono finché la proposta è in bozza o, per chi approva, in approvazione: ricarica la pagina');
    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('documentSlides.frozen', true)->where('documentSlides.editable', false)->where('documentSlides.includedSlides', 4));
});

it('D-418: "Solo riepilogo" makes a PDF of cover, recap and closing', function () {
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);
    dslSave($this->agent, $proposal, 'summary', [])->assertSessionHasNoErrors();
    dslSubmit($this->agent, $proposal);

    $this->actingAs($this->manager)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id), ['discounts' => []])
        ->assertSessionHasNoErrors();

    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();

    expect(array_column($document->page_model['slides'], 'title'))->toBe(['Copertina Lazio', 'Recap Lazio', 'Contatti Lazio'])
        ->and($document->page_model['skipped'])->toBe([])
        ->and($document->page_model['document_mode'])->toBe('summary');
});

it('D-420: the thumbnail of a slide image is a JPEG of 480×270, made once', function () {
    $response = $this->actingAs($this->agent)->get(route('files.thumbnail', $this->benefit->image_file_id))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/jpeg');

    $derivative = StoredFileDerivative::query()->sole();
    $thumbnail = StoredFile::query()->findOrFail($derivative->derivative_file_id);

    expect($derivative->original_file_id)->toBe($this->benefit->image_file_id)
        ->and($derivative->kind)->toBe(FileDerivativeKind::Thumbnail)
        ->and($thumbnail->purpose)->toBe(FilePurpose::Thumbnail)
        ->and([$thumbnail->width, $thumbnail->height])->toBe([480, 270]);

    $this->actingAs($this->manager)->get(route('files.thumbnail', $this->benefit->image_file_id))->assertOk();
    expect(StoredFileDerivative::query()->count())->toBe(1)
        ->and(app(FileDerivatives::class)->thumbnail(StoredFile::query()->findOrFail($this->benefit->image_file_id))->id)->toBe($thumbnail->id);

    // Who sees the original sees its thumbnail, also from files.download.
    $this->actingAs($this->agent)->get(route('files.download', $thumbnail))->assertOk();
});

it('D-420: no thumbnail for other files; the agent does not see the images of inactive slides', function () {
    $this->actingAs($this->admin)->get(route('files.thumbnail', storeTestFile(FilePurpose::ClientLogo)))->assertNotFound();

    $this->benefit->forceFill(['is_active' => false])->save();

    $this->actingAs($this->agent)->get(route('files.thumbnail', $this->benefit->image_file_id))->assertForbidden();
    $this->actingAs($this->manager)->get(route('files.thumbnail', $this->benefit->image_file_id))->assertOk();
});

it('D-420: the derived files are never changed nor deleted', function () {
    app(FileDerivatives::class)->thumbnail(StoredFile::query()->findOrFail($this->benefit->image_file_id));

    expect(fn () => DB::table('stored_file_derivatives')->update(['kind' => 'thumbnail']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('stored_file_derivatives')->delete())->toThrow(QueryException::class)
        ->and(fn () => StoredFileDerivative::query()->sole()->save())->toThrow(LogicException::class);
});

/** DM-06: the generic configuration of Infront of a kind of photo, on the lower row of the sample photo. */
function dslLedSetting(LedPhotoKind $kind): LedMockupSetting
{
    $photo = storeTestFile(FilePurpose::FieldPhoto, null, SampleDeck::fieldPhoto(), 'campo.png');

    return LedMockupSetting::query()->create([
        'club_id' => null,
        'photo_kind' => $kind,
        'photo_file_id' => $photo->id,
        'areas' => [[[0, 224], [1768, 224], [1768, 262], [0, 262]]],
        'logo_height_percent' => 85,
        'spacing' => 30,
    ]);
}

function dslApprove(User $admin, Proposal $proposal): void
{
    test()->actingAs($admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id), ['discounts' => []])
        ->assertSessionHasNoErrors();
}

it('D-398, D-423: the PDF has the LED simulation of Lazio on the generic photo of the 2nd row', function () {
    $setting = dslLedSetting(LedPhotoKind::Row2);
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);
    dslSubmit($this->agent, $proposal);
    dslApprove($this->admin, $proposal);

    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();
    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);
    $led = $renderer->rendered[0]['pages'][2];
    assert($led instanceof LayoutPage);

    expect(array_column($document->page_model['slides'], 'title'))->toBe(['Copertina Lazio', 'Benefit Lazio', 'Simulazione LED', 'Recap Lazio', 'Contatti Lazio'])
        ->and($document->page_model['slides'][2])->toMatchArray(['club_id' => $this->lazio->id, 'format' => 'FILA_2', 'pages' => 1])
        ->and($document->page_model['skipped'])->toBe([])
        ->and($led->backgroundPath)->toBeNull()
        ->and($led->data['style'])->toBe(LedMockupLayout::STYLE_ROWS)
        ->and($led->data['areas'])->toBe($setting->areas)
        ->and([$led->data['photo_width'], $led->data['photo_height']])->toBe([1768, 726])
        ->and($led->data['photo'])->toBe(Storage::disk(StoredFile::DISK)->path(StoredFile::query()->findOrFail($setting->photo_file_id)->path));
});

it('D-427: without a configuration the LED page is skipped, who approves is warned and the document is generated', function () {
    dslLedSetting(LedPhotoKind::Row1);
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);
    dslSubmit($this->agent, $proposal);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('documentSlides.warnings', [
            'Simulazione LED non configurata per SS Lazio (2ª fila): la pagina non entra nel documento',
        ]));

    dslApprove($this->admin, $proposal);

    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();

    expect($document->status->value)->toBe('ready')
        ->and(array_column($document->page_model['slides'], 'title'))->toBe(['Copertina Lazio', 'Benefit Lazio', 'Recap Lazio', 'Contatti Lazio'])
        ->and($document->page_model['skipped'])->toBe([['slide_id' => $this->led->id, 'layout' => 'led_mockup', 'club_id' => $this->lazio->id, 'format' => 'FILA_2']]);
});

it('D-426: in the step "Documento" the LED simulation shows the thumbnail of the photo of the field', function () {
    $setting = dslLedSetting(LedPhotoKind::Row2);
    $proposal = dslDraft($this->agent, [$this->lazio->id => 60]);

    $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentSlides.sections.2.slides.0.kind', 'led')
            ->where('documentSlides.sections.2.slides.0.thumbnailUrl', route('files.thumbnail', $setting->photo_file_id)));

    $this->actingAs($this->agent)->get(route('files.thumbnail', $setting->photo_file_id))->assertOk();
});
