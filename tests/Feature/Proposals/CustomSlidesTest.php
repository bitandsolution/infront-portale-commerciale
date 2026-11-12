<?php

use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Data\LayoutPage;
use App\Enums\AuditAction;
use App\Enums\CustomSlideLayout;
use App\Enums\DocumentLayout;
use App\Enums\FilePurpose;
use App\Enums\SlideDocumentType;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\Document;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroupFile;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Documents\SampleDeck;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakePdfRenderer;

/*
| Slides composed with blocks (D-399, D-428…D-432; slice DM, PR DM-07): added, written and deleted by
| the agent in the step "Documento" while the group is a draft, by admin and manager while it waits;
| placed at the end of their section on the background "Slide personalizzata" of the library; their
| tick saved with the slides of the document; frozen with the versions at the approval and drawn in
| the PDF; the warning when the content does not fit; the preview drawn by the renderer. The routes
| are in the route × role matrix (RouteMatrixTest).
|
| Club document of SS Lazio: cover, the LED simulation and the background of the composed slides in
| Brand exposure, recap and closing "Contatti Lazio".
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->lazio = cstClub($this, 'SS Lazio', 'LAZIO');

    $this->cover = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('INTRO')->layout(DocumentLayout::Cover)->create(['title' => 'Copertina Lazio']);
    $this->led = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('BRAND_EXPOSURE')->layout(DocumentLayout::LedMockup)->create(['title' => 'Simulazione LED']);
    $this->background = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('BRAND_EXPOSURE')->layout(DocumentLayout::CustomBlocks)->create(['title' => 'Sfondo personalizzata Lazio']);
    $this->recap = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('RECAP')->layout(DocumentLayout::ClubRecap)->document(SlideDocumentType::Club)->create(['title' => 'Recap Lazio']);
    $this->closing = Slide::factory()->forSeason($this->season)->forClub($this->lazio)->section('CLOSING')->create(['title' => 'Contatti Lazio']);

    $this->agent = User::factory()->agent()->create();
    $this->otherAgent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
});

/** A club with the tier TOP and the 2nd row, priced and available. */
function cstClub(object $test, string $name, string $code): Club
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

/** A draft of the agent for a new client with Lazio (tier TOP, 1′ of the 2nd row). */
function cstDraft(User $agent, Club $club): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    $version = $proposal->workingVersion;

    test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => [[
                'club_id' => $club->id,
                'partnership_tier_id' => PartnershipTier::query()->where('code', 'TOP')->value('id'),
                'led_format_id' => LedFormat::query()->where('code', 'FILA_2')->value('id'),
                'led_seconds' => 60,
                'seats' => [],
            ]],
        ])
        ->assertSessionHasNoErrors();

    return $proposal->refresh();
}

function cstAdd(User $actor, Proposal $proposal): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->post(route('proposals.groups.custom-slides.store', $proposal->proposal_group_id));
}

/**
 * @param  array<string, mixed>  $data
 */
function cstSave(User $actor, ProposalCustomSlide $slide, array $data): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.edit', ['proposal' => Proposal::query()->where('proposal_group_id', $slide->proposal_group_id)->value('id'), 'passo' => 6]))
        ->put(route('proposals.groups.custom-slides.update', ['group' => $slide->proposal_group_id, 'customSlide' => $slide->id]), [
            'title' => 'Perché Lazio',
            'layout' => 'one_column',
            'section' => 'BRAND_EXPOSURE',
            'image_file_id' => null,
            'blocks' => [],
            ...$data,
        ]);
}

function cstUpload(User $actor, ProposalCustomSlide $slide, ?string $block): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.edit', ['proposal' => Proposal::query()->where('proposal_group_id', $slide->proposal_group_id)->value('id'), 'passo' => 6]))
        ->post(route('proposals.groups.custom-slides.image', ['group' => $slide->proposal_group_id, 'customSlide' => $slide->id]), [
            'image' => UploadedFile::fake()->createWithContent('immagine.png', SampleDeck::clientLogo()),
            'block' => $block ?? '',
        ]);
}

function cstSlide(Proposal $proposal): ProposalCustomSlide
{
    return ProposalCustomSlide::query()->where('proposal_group_id', $proposal->proposal_group_id)->latest('id')->firstOrFail();
}

function cstSubmit(User $agent, Proposal $proposal): void
{
    test()->actingAs($agent)->post(route('proposals.groups.submit', $proposal->proposal_group_id))->assertSessionHasNoErrors();
}

function cstApprove(User $admin, Proposal $proposal): void
{
    test()->actingAs($admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id), ['discounts' => []])
        ->assertSessionHasNoErrors();
}

/**
 * Titles of the slides listed by the step "Documento", in the order of the document.
 *
 * @return list<string>
 */
function cstListed(TestResponse $response): array
{
    $titles = [];
    $response->assertInertia(function (Assert $page) use (&$titles): void {
        foreach ($page->toArray()['props']['documentSlides']['sections'] as $section) {
            foreach ($section['slides'] as $slide) {
                $titles[] = $slide['title'];
            }
        }
    });

    return $titles;
}

it('D-399: the agent adds a composed slide: one column, at the end of Brand exposure, a title and a text block, audit', function () {
    $proposal = cstDraft($this->agent, $this->lazio);

    cstAdd($this->agent, $proposal)->assertSessionHasNoErrors()->assertRedirect(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]));

    $slide = cstSlide($proposal);
    $event = soleAuditEvent(AuditAction::ProposalCustomSlideCreated);

    expect($slide->title)->toBe('Slide personalizzata 1')
        ->and($slide->layout)->toBe(CustomSlideLayout::OneColumn)
        ->and($slide->section->code)->toBe('BRAND_EXPOSURE')
        ->and($slide->is_included)->toBeTrue()
        ->and(array_column($slide->blocks, 'type'))->toBe(['title', 'text'])
        ->and($event->user_id)->toBe($this->agent->id)
        ->and($event->object_id)->toBe($proposal->id)
        ->and($event->new_values)->toMatchArray(['proposal_group_id' => $proposal->proposal_group_id, 'custom_slide' => 'Slide personalizzata 1', 'section' => 'BRAND_EXPOSURE']);

    $response = $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('documentSlides.custom.editable', true)
        ->where('documentSlides.custom.canAdd', true)
        ->where('documentSlides.custom.max', 3)
        ->where('documentSlides.custom.slides.0.title', 'Slide personalizzata 1')
        ->where('documentSlides.custom.noBackground', null)
        ->where('documentSlides.totalSlides', 5));

    // D-429: at the end of Brand exposure, right before the recap; the background is never listed.
    expect(cstListed($response))->toBe(['Copertina Lazio', 'Simulazione LED', 'Slide personalizzata 1', 'Recap Lazio', 'Contatti Lazio']);
});

it('D-399: at most three composed slides per group; another agent is refused', function () {
    $proposal = cstDraft($this->agent, $this->lazio);

    foreach ([1, 2, 3] as $number) {
        cstAdd($this->agent, $proposal)->assertSessionHasNoErrors();
    }

    cstAdd($this->agent, $proposal)->assertSessionHas('toast.message', 'Una proposta ha al massimo 3 slide personalizzate');
    cstAdd($this->otherAgent, $proposal)->assertForbidden();

    expect(ProposalCustomSlide::query()->where('proposal_group_id', $proposal->proposal_group_id)->pluck('title')->all())
        ->toBe(['Slide personalizzata 1', 'Slide personalizzata 2', 'Slide personalizzata 3']);
});

it('D-430: the content is saved normalized, with one audit event of the fields changed', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);

    cstSave($this->agent, $slide, [
        'layout' => 'two_columns',
        'section' => 'BENEFIT',
        'blocks' => [
            ['key' => 'tit', 'type' => 'title', 'text' => "  La   proposta\nLazio  ", 'column' => 'left'],
            ['key' => 'txt', 'type' => 'text', 'text' => "Un pacchetto **su misura**\r\n- LED\r\n- Hospitality", 'column' => 'right', 'rows' => [['ignorata']]],
            ['key' => 'tab', 'type' => 'table', 'rows' => [['Club', 'Format'], ['Lazio']]],
        ],
    ])->assertSessionHasNoErrors();

    $slide->refresh();

    expect($slide->layout)->toBe(CustomSlideLayout::TwoColumns)
        ->and($slide->section->code)->toBe('BENEFIT')
        ->and($slide->title)->toBe('Perché Lazio')
        ->and($slide->blocks)->toBe([
            ['key' => 'tit', 'type' => 'title', 'column' => 'left', 'text' => 'La proposta Lazio'],
            ['key' => 'txt', 'type' => 'text', 'column' => 'right', 'text' => "Un pacchetto **su misura**\n- LED\n- Hospitality"],
            ['key' => 'tab', 'type' => 'table', 'column' => 'left', 'rows' => [['Club', 'Format'], ['Lazio', '']]],
        ]);

    $event = auditEventsOf(AuditAction::ProposalCustomSlideUpdated)->sole();
    expect($event->new_values)->toHaveKeys(['proposal_group_id', 'custom_slide_id', 'custom_slide', 'layout', 'section', 'blocks'])
        ->and($event->old_values['layout'])->toBe('one_column');

    // The same content again: nothing written.
    cstSave($this->agent, $slide, [
        'layout' => 'two_columns',
        'section' => 'BENEFIT',
        'blocks' => $slide->blocks,
    ])->assertSessionHasNoErrors();
    expect(auditEventsOf(AuditAction::ProposalCustomSlideUpdated))->toHaveCount(1);
});

it('D-430: more than 6 blocks, a table beyond 6 × 4 or more than 2 images are refused under the fields', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);
    $before = $slide->blocks;

    $blocks = array_map(fn (int $index): array => ['key' => 'b'.$index, 'type' => 'text', 'text' => 'Testo'], range(1, 7));
    cstSave($this->agent, $slide, ['blocks' => $blocks])->assertSessionHasErrors(['blocks' => 'Una slide ha al massimo 6 blocchi.']);

    cstSave($this->agent, $slide, ['blocks' => [['key' => 't', 'type' => 'table', 'rows' => array_fill(0, 7, ['a'])]]])
        ->assertSessionHasErrors(['blocks.0.rows' => 'La tabella ha al massimo 6 righe.']);
    cstSave($this->agent, $slide, ['blocks' => [['key' => 't', 'type' => 'table', 'rows' => [['a', 'b', 'c', 'd', 'e']]]]])
        ->assertSessionHasErrors(['blocks.0.rows.0' => 'La tabella ha al massimo 4 colonne.']);

    // The image of the layout "image_text" counts: with two image blocks they are three.
    cstSave($this->agent, $slide, ['layout' => 'image_text', 'blocks' => [
        ['key' => 'i1', 'type' => 'image'],
        ['key' => 'i2', 'type' => 'image_text', 'text' => 'Testo'],
    ]])->assertSessionHasErrors(['blocks' => 'Una slide ha al massimo 2 immagini, compresa quella dello schema "Immagine a sinistra".']);

    // An image of another group is not accepted.
    $foreign = storeTestFile(FilePurpose::CustomSlideImage, null, SampleDeck::clientLogo(), 'altra.png');
    cstSave($this->agent, $slide, ['blocks' => [['key' => 'i1', 'type' => 'image', 'file_id' => $foreign->id]]])
        ->assertSessionHasErrors(['blocks' => 'Immagine non trovata: caricala di nuovo.']);

    expect($slide->refresh()->blocks)->toBe($before)
        ->and(auditEventsOf(AuditAction::ProposalCustomSlideUpdated))->toHaveCount(0);
});

it('D-430: an image uploaded for a block is re-encoded and seen only by who sees the group', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);
    cstSave($this->agent, $slide, ['blocks' => [['key' => 'img', 'type' => 'image']]])->assertSessionHasNoErrors();

    cstUpload($this->agent, $slide, 'img')->assertSessionHasNoErrors();

    $slide->refresh();
    $fileId = $slide->blocks[0]['file_id'];
    $file = StoredFile::query()->findOrFail($fileId);

    expect($file->purpose)->toBe(FilePurpose::CustomSlideImage)
        ->and(ProposalGroupFile::query()->where('proposal_group_id', $proposal->proposal_group_id)->pluck('stored_file_id')->all())->toBe([$fileId])
        ->and(auditEventsOf(AuditAction::ProposalCustomSlideUpdated)->last()?->new_values)->toHaveKey('blocks');

    $this->actingAs($this->agent)->get(route('files.download', $file))->assertOk();
    $this->actingAs($this->agent)->get(route('files.thumbnail', $file))->assertOk();
    $this->actingAs($this->manager)->get(route('files.download', $file))->assertOk();
    $this->actingAs($this->otherAgent)->get(route('files.download', $file))->assertForbidden();

    // The image of the slide only with the layout "image_text"; a block without image refused.
    cstUpload($this->agent, $slide, null)->assertSessionHas('toast.message', 'L\'immagine non ha più il suo blocco nella slide: ricarica la pagina');
    cstUpload($this->agent, $slide, 'nessuno')->assertSessionHas('toast.message', 'L\'immagine non ha più il suo blocco nella slide: ricarica la pagina');

    // A file that is not an image is refused under the field.
    $this->actingAs($this->agent)
        ->post(route('proposals.groups.custom-slides.image', ['group' => $slide->proposal_group_id, 'customSlide' => $slide->id]), [
            'image' => UploadedFile::fake()->createWithContent('testo.png', 'non è un\'immagine'),
            'block' => 'img',
        ])
        ->assertSessionHasErrors('image');
});

it('D-397: once sent, the agent no longer changes the composed slides; admin and manager do, only while the group waits', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);

    cstSave($this->manager, $slide, ['title' => 'Dal gestore'])
        ->assertSessionHas('toast.message', 'Le slide del documento si scelgono finché la proposta è in bozza o, per chi approva, in approvazione: ricarica la pagina');

    cstSubmit($this->agent, $proposal);

    cstSave($this->agent, $slide, ['title' => 'Dall\'agente'])
        ->assertSessionHas('toast.message', 'Le slide del documento si scelgono finché la proposta è in bozza o, per chi approva, in approvazione: ricarica la pagina');
    cstSave($this->manager, $slide, ['title' => 'Dal gestore'])->assertSessionHasNoErrors();

    expect($slide->refresh()->title)->toBe('Dal gestore');

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('documentSlides.custom.editable', true)->where('documentSlides.custom.slides.0.updateUrl', route('proposals.groups.custom-slides.update', ['group' => $proposal->proposal_group_id, 'customSlide' => $slide->id])));
    $this->actingAs($this->agent)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('documentSlides.custom.editable', false)->where('documentSlides.custom.slides.0.updateUrl', null));
});

it('D-397: the tick of a composed slide is saved with the slides; "Solo riepilogo" leaves it out', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);

    $this->actingAs($this->agent)
        ->put(route('proposals.groups.slides', $proposal->proposal_group_id), ['mode' => 'complete', 'excluded_slide_ids' => [], 'excluded_custom_slide_ids' => [$slide->id]])
        ->assertSessionHasNoErrors();

    expect($slide->refresh()->is_included)->toBeFalse()
        ->and(soleAuditEvent(AuditAction::ProposalDocumentSlidesUpdated)->new_values)->toBe(['proposal_group_id' => $proposal->proposal_group_id, 'excluded_custom_slides' => 'Slide personalizzata 1']);

    // Without the list the ticks of the composed slides stay as they are.
    $this->actingAs($this->agent)
        ->put(route('proposals.groups.slides', $proposal->proposal_group_id), ['mode' => 'summary', 'excluded_slide_ids' => []])
        ->assertSessionHasNoErrors();
    expect($slide->refresh()->is_included)->toBeFalse();

    $this->actingAs($this->agent)
        ->put(route('proposals.groups.slides', $proposal->proposal_group_id), ['mode' => 'summary', 'excluded_slide_ids' => [], 'excluded_custom_slide_ids' => []])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentSlides.sections.1.slides.1.custom', true)
            ->where('documentSlides.sections.1.slides.1.excluded', false)
            ->where('documentSlides.sections.1.slides.1.included', false)
            ->where('documentSlides.sections.1.slides.1.locked', true));
});

it('D-428: without the background of the library the composed slides stay out, with a warning to who approves', function () {
    $this->background->forceFill(['is_active' => false])->save();
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);

    $response = $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]));
    $response->assertInertia(fn (Assert $page) => $page
        ->where('documentSlides.custom.noBackground', 'Lo sfondo della slide personalizzata non è ancora nella libreria: la slide entra nel documento quando Infront lo aggiunge.')
        ->where('documentSlides.totalSlides', 4));
    expect(cstListed($response))->not->toContain('Slide personalizzata 1');

    cstSubmit($this->agent, $proposal);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('documentSlides.warnings', fn ($warnings): bool => collect($warnings)->contains(
            'Manca lo sfondo attivo della slide personalizzata per il documento club: le slide personalizzate non entrano nel documento',
        )));
});

it('D-419: the approval freezes the composed slides on the versions and the PDF draws them on the background', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);
    cstSave($this->agent, $slide, ['title' => 'Perché Lazio', 'blocks' => [['key' => 'tit', 'type' => 'title', 'text' => 'La nostra proposta']]])->assertSessionHasNoErrors();
    cstSubmit($this->agent, $proposal);
    cstApprove($this->admin, $proposal);

    $version = ProposalVersion::query()->findOrFail($proposal->refresh()->current_version_id);
    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();
    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);
    $titles = array_column($document->page_model['slides'], 'title');
    $page = $renderer->rendered[0]['pages'][array_search('Perché Lazio', $titles, true)];
    assert($page instanceof LayoutPage);

    expect($version->custom_slides)->toBe([[
        'id' => $slide->id,
        'sort_order' => 1,
        'title' => 'Perché Lazio',
        'layout' => 'one_column',
        'section' => 'BRAND_EXPOSURE',
        'image_file_id' => null,
        'blocks' => [['key' => 'tit', 'type' => 'title', 'column' => 'left', 'text' => 'La nostra proposta']],
        'included' => true,
    ]])
        ->and($titles)->toContain('Perché Lazio')
        ->and($titles[array_search('Perché Lazio', $titles, true) + 1])->toBe('Recap Lazio')
        ->and($document->page_model['excluded_custom_slide_ids'])->toBe([])
        ->and($page->layout)->toBe(DocumentLayout::CustomBlocks)
        ->and($page->backgroundPath)->toBe(Storage::disk(StoredFile::DISK)->path(StoredFile::query()->findOrFail($this->background->image_file_id)->path))
        ->and($page->data['slide']->title)->toBe('Perché Lazio');

    // A later change of the row (impossible from the portal) does not touch the frozen copy.
    $slide->refresh()->forceFill(['title' => 'Cambiata'])->save();
    $this->actingAs($this->admin)->post(route('documents.regenerate', $proposal->proposal_group_id))->assertSessionHasNoErrors();
    $regenerated = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();
    expect(array_column($regenerated->page_model['slides'], 'title'))->toContain('Perché Lazio')->not->toContain('Cambiata');

    // After the approval nobody changes the composed slides.
    cstSave($this->admin, $slide, ['title' => 'Dopo'])
        ->assertSessionHas('toast.message', 'Le slide del documento si scelgono finché la proposta è in bozza o, per chi approva, in approvazione: ricarica la pagina');
});

it('D-431: a content that does not fit is warned in the step "Documento"; a smaller one is only reduced', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);
    $long = str_repeat('Un testo molto lungo che occupa la slide per intero e va oltre lo spazio disponibile. ', 11);

    cstSave($this->agent, $slide, ['blocks' => [
        ['key' => 'a', 'type' => 'text', 'text' => $long],
        ['key' => 'b', 'type' => 'text', 'text' => $long],
        ['key' => 'd', 'type' => 'text', 'text' => $long],
        ['key' => 'c', 'type' => 'table', 'rows' => array_fill(0, 6, ['Uno', 'Due', 'Tre', 'Quattro'])],
    ]])->assertSessionHasNoErrors();

    $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentSlides.custom.slides.0.warning', 'Il contenuto non entra nella slide: nel PDF viene ridotto al 70% e la parte che non entra viene tagliata con "…".')
            ->where('documentSlides.custom.slides.0.reduced', null));

    cstSave($this->agent, $slide, ['blocks' => [['key' => 'a', 'type' => 'text', 'text' => $long]]])->assertSessionHasNoErrors();

    $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->assertInertia(fn (Assert $page) => $page->where('documentSlides.custom.slides.0.warning', null));
});

it('D-432: the preview is the saved slide drawn by the renderer on the background, for who sees the group', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);
    $url = route('proposals.groups.custom-slides.preview', ['group' => $proposal->proposal_group_id, 'customSlide' => $slide->id]);

    $response = $this->actingAs($this->agent)->get($url)->assertOk();
    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);
    $page = $renderer->screenshots[0];
    assert($page instanceof LayoutPage);

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($page->layout)->toBe(DocumentLayout::CustomBlocks)
        ->and($page->backgroundPath)->toBe(Storage::disk(StoredFile::DISK)->path(StoredFile::query()->findOrFail($this->background->image_file_id)->path));

    $this->actingAs($this->manager)->get($url)->assertOk();
    $this->actingAs($this->otherAgent)->get($url)->assertForbidden();
});

it('D-399: the agent deletes a composed slide; the audit keeps its content', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $proposal);
    $slide = cstSlide($proposal);

    $this->actingAs($this->agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->delete(route('proposals.groups.custom-slides.destroy', ['group' => $proposal->proposal_group_id, 'customSlide' => $slide->id]))
        ->assertSessionHas('toast.message', 'Slide personalizzata «Slide personalizzata 1» eliminata');

    expect(ProposalCustomSlide::query()->count())->toBe(0)
        ->and(soleAuditEvent(AuditAction::ProposalCustomSlideDeleted)->old_values)->toMatchArray(['custom_slide' => 'Slide personalizzata 1', 'layout' => 'one_column']);
});

it('D-399: a composed slide of another group is not reached through this group', function () {
    $proposal = cstDraft($this->agent, $this->lazio);
    $other = cstDraft($this->agent, $this->lazio);
    cstAdd($this->agent, $other);
    $slide = cstSlide($other);

    $this->actingAs($this->agent)
        ->put(route('proposals.groups.custom-slides.update', ['group' => $proposal->proposal_group_id, 'customSlide' => $slide->id]), ['title' => 'X', 'layout' => 'one_column', 'section' => 'BRAND_EXPOSURE', 'blocks' => []])
        ->assertNotFound();
});
