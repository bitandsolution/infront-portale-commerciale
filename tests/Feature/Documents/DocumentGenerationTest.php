<?php

use App\Domain\Documents\Actions\RequestDocument;
use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Data\LayoutPage;
use App\Domain\Documents\Data\RecapRow;
use App\Domain\Documents\Data\StaticSlidePage;
use App\Domain\Documents\Layouts\RecapStyle;
use App\Domain\Documents\Services\DocumentGenerator;
use App\Enums\AuditAction;
use App\Enums\DocumentLayout;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Enums\SlideDocumentType;
use App\Jobs\GenerateDocument;
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
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakePdfRenderer;

/*
| PDF document of an approved group (M09 FL-01, F-01, F-05, §6, §9; D-090, D-111, D-125, D-205,
| D-396, D-413…D-417; slice DM, PR DM-04). The approval asks for the document, the job of the queue
| "documents" composes it from the frozen versions and renders it; in the tests the queue is
| synchronous and the renderer is FakePdfRenderer (no Chromium), so the pages it receives are checked
| here and the drawing in tests/Pdf. The route is in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->tier = PartnershipTier::query()->where('code', 'TOP')->firstOrFail();
    $this->format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();
    $this->pool = InventoryPool::query()->where('code', 'FILA_2')->firstOrFail();

    $this->lazio = docgClub($this, 'SS Lazio', 'LAZIO', '#87D8F7');
    $this->agent = User::factory()->agent()->create();
    $this->otherAgent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
});

/** A club with the tier TOP at 155,680.00 € and the 2nd row at 1,000.00 € per minute. */
function docgClub(object $test, string $name, string $code, ?string $color = null): Club
{
    $club = Club::factory()->infront()->create([
        'name' => $name,
        'code' => $code,
        'short_name' => ucfirst(strtolower($code)),
        'primary_color' => $color,
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
 * Saves the clubs of a draft as the configurator does: club id → [tier, seconds of the 2nd row].
 *
 * @param  array<int, array{tier?: bool, seconds?: int}>  $clubs
 */
function docgSave(User $agent, Proposal $proposal, array $clubs): void
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

/**
 * A group sent for approval, one variant per configuration (A, B…), for a client with a logo.
 *
 * @param  list<array<int, array{tier?: bool, seconds?: int}>>  $variants
 */
function docgGroup(User $agent, array $variants): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create(['company_name' => 'Caffè Rossi S.p.A.']);
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $first = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    docgSave($agent, $first, $variants[0]);

    foreach (array_slice($variants, 1) as $clubs) {
        test()->actingAs($agent)
            ->from(route('proposals.edit', ['proposal' => $first, 'passo' => 5]))
            ->post(route('proposals.variants.store', $first), ['step' => 5])
            ->assertSessionHasNoErrors();
        $variant = Proposal::query()->where('proposal_group_id', $first->proposal_group_id)->latest('id')->firstOrFail();
        docgSave($agent, $variant, $clubs);
    }

    test()->actingAs($agent)->post(route('proposals.groups.submit', $first->proposal_group_id))->assertSessionHasNoErrors();

    return $first->refresh();
}

/**
 * @param  array<int, array{type: string, value: string}>  $discounts
 */
function docgApprove(User $actor, Proposal $proposal, array $discounts = []): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id), ['discounts' => $discounts]);
}

/** Cover, recap and a closing slide of a club, for the club document. */
function docgClubSlides(object $test, Club $club, bool $withCover = true): void
{
    if ($withCover) {
        Slide::factory()->forSeason($test->season)->forClub($club)->section('INTRO')->layout(DocumentLayout::Cover)->create(['title' => 'Copertina Lazio']);
    }

    Slide::factory()->forSeason($test->season)->forClub($club)->section('RECAP')->layout(DocumentLayout::ClubRecap)->document(SlideDocumentType::Club)->create(['title' => 'Recap Lazio']);
    Slide::factory()->forSeason($test->season)->forClub($club)->section('CLOSING')->create(['title' => 'Contatti Lazio']);
}

/** Cover, recap and a closing slide of Infront, for the network document. */
function docgNetworkSlides(object $test): void
{
    Slide::factory()->forSeason($test->season)->section('INTRO')->layout(DocumentLayout::Cover)->create(['title' => 'Copertina network']);
    Slide::factory()->forSeason($test->season)->section('RECAP')->layout(DocumentLayout::NetworkRecap)->document(SlideDocumentType::Network)->create(['title' => 'Recap network']);
    Slide::factory()->forSeason($test->season)->section('CLOSING')->create(['title' => 'Contatti Infront']);
}

function docgRenderer(): FakePdfRenderer
{
    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);

    return $renderer;
}

function docgCurrent(Proposal $proposal): Document
{
    return Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();
}

it('M09 FL-01: the approval generates the club document: cover, club recap, closing; file named as RB-09', function () {
    docgClubSlides($this, $this->lazio);
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true, 'seconds' => 60]]]);

    docgApprove($this->admin, $a, [$a->id => ['type' => 'percent', 'value' => '15']])->assertSessionHasNoErrors();

    $document = docgCurrent($a);
    $file = StoredFile::query()->findOrFail($document->file_id);

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->document_type)->toBe(DocumentType::Club)
        ->and($document->proposal_id)->toBe($a->id)
        ->and($document->proposal_version_id)->toBe($a->refresh()->current_version_id)
        ->and($document->attempts)->toBe(1)
        ->and($document->page_count)->toBe(3)
        ->and($document->generated_by)->toBeNull()
        ->and($file->purpose)->toBe(FilePurpose::GeneratedPdf)
        ->and($file->downloadName())->toBe('Infront_Proposta_Caffe-Rossi-S-p-A_Lazio_2027-28_v1.pdf')
        ->and($document->page_model['variants'])->toHaveCount(1)
        ->and(array_column($document->page_model['slides'], 'title'))->toBe(['Copertina Lazio', 'Recap Lazio', 'Contatti Lazio'])
        ->and($document->kpi_snapshot['A']['total_matches'])->toBe(19)
        ->and($document->kpi_snapshot['A']['final_total'])->toBe('132328.00');

    $pages = docgRenderer()->rendered[0]['pages'];
    expect($pages[0])->toBeInstanceOf(LayoutPage::class)
        ->and($pages[0]->layout)->toBe(DocumentLayout::Cover)
        ->and($pages[0]->data['date'])->toBe('24/05/2027')
        ->and($pages[0]->data['logo'])->toBeString()
        ->and($pages[1]->layout)->toBe(DocumentLayout::ClubRecap)
        ->and($pages[2])->toBeInstanceOf(StaticSlidePage::class)
        ->and(docgRenderer()->rendered[0]['metadata']->title)->toBe('Infront Proposta – Caffè Rossi S.p.A.');

    expect(auditEventsOf(AuditAction::DocumentRequested))->toHaveCount(1)
        ->and(soleAuditEvent(AuditAction::DocumentGenerated)->new_values)->toMatchArray(['status' => 'ready', 'pages' => 3]);
});

it('D-415: the club recap has the partnership and LED rows, the club colour and the discounted total', function () {
    docgClubSlides($this, $this->lazio);
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true, 'seconds' => 60]]]);

    docgApprove($this->admin, $a, [$a->id => ['type' => 'percent', 'value' => '15']])->assertSessionHasNoErrors();

    $recap = docgRenderer()->rendered[0]['pages'][1];
    $style = $recap->data['style'];
    /** @var list<RecapRow> $rows */
    $rows = $recap->data['rows'];

    expect($style)->toBeInstanceOf(RecapStyle::class)
        ->and($style->headerBackground)->toBe('#87D8F7')
        ->and($style->firstColumnTitle)->toBe('SS LAZIO')
        ->and($recap->data['title'])->toBeNull()
        ->and(array_map(fn (RecapRow $row): string => $row->label, $rows))->toBe(['Top Partner', 'LED'])
        ->and($rows[1]->format)->toBe($this->format->name)
        ->and($rows[1]->matches)->toBe(19)
        ->and($rows[1]->secondsPerMatch)->toBe(60)
        ->and($rows[1]->spotsPerMatch)->toBe(4)
        ->and($recap->data['totals']->listTotal)->toBe($a->refresh()->currentVersion?->list_total)
        ->and($recap->data['totals']->hasDiscount())->toBeTrue()
        ->and($recap->data['totals']->seconds)->toBe(60 * 19)
        ->and($recap->data['totals']->spots)->toBe(4 * 19);
});

it('D-396: a network document of two variants has one recap per variant, titled "Proposta A" and "Proposta B"', function () {
    docgNetworkSlides($this);
    $roma = docgClub($this, 'AS Roma', 'ROMA');
    $a = docgGroup($this->agent, [
        [$this->lazio->id => ['tier' => true], $roma->id => ['seconds' => 30]],
        [$this->lazio->id => ['seconds' => 60], $roma->id => ['tier' => true, 'seconds' => 60]],
    ]);

    docgApprove($this->admin, $a)->assertSessionHasNoErrors();

    $document = docgCurrent($a);
    $pages = docgRenderer()->rendered[0]['pages'];
    $recaps = array_values(array_filter($pages, fn ($page): bool => $page instanceof LayoutPage && $page->layout === DocumentLayout::NetworkRecap));

    expect($document->document_type)->toBe(DocumentType::Network)
        ->and($document->page_count)->toBe(4)
        ->and(array_column($document->page_model['variants'], 'letter'))->toBe(['A', 'B'])
        ->and($recaps)->toHaveCount(2)
        ->and($recaps[0]->data['title'])->toBe('Proposta A')
        ->and($recaps[1]->data['title'])->toBe('Proposta B')
        ->and(array_map(fn (RecapRow $row): string => $row->format, $recaps[0]->data['rows']))->toBe(['TOP PARTNER', $this->format->name])
        ->and(array_map(fn (RecapRow $row): string => $row->format, $recaps[1]->data['rows']))->toBe([$this->format->name, 'TOP PARTNER · '.$this->format->name])
        ->and($recaps[0]->data['rows'][0]->logoPath)->toBeString()
        ->and($recaps[1]->data['totals']->matches)->toBe(38)
        ->and($document->file_id)->not->toBeNull();
});

it('D-416: without a cover the approval goes through and the document fails with the reason', function () {
    docgClubSlides($this, $this->lazio, withCover: false);
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);

    docgApprove($this->admin, $a)->assertSessionHasNoErrors();

    $document = docgCurrent($a);
    expect($a->refresh()->status->value)->toBe('approved')
        ->and($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->file_id)->toBeNull()
        ->and($document->last_error)->toContain('Manca la copertina attiva per il documento club nella libreria della stagione 2027-28')
        ->and(docgRenderer()->rendered)->toBe([])
        ->and(soleAuditEvent(AuditAction::DocumentFailed)->reason)->toContain('Manca la copertina');

    // Admin and manager read the reason; the agent only that the document is not ready.
    $this->actingAs($this->manager)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.document.current.status', 'failed')
            ->where('group.document.current.error', $document->last_error)
            ->where('group.document.canRegenerate', false));
    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.document.current.statusLabel', 'Non riuscito')
            ->where('group.document.current.error', null));
});

it('D-416: admin and manager are warned before the approval of what the library lacks', function () {
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.documentWarnings.0', 'Manca la copertina attiva per il documento club')
            ->where('group.document', null)
            ->where('group.canApprove', true));
    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.documentWarnings', []));
});

it('D-205, D-414: the admin regenerates after the choice of the client: only the chosen variant, the old one admin only', function () {
    docgClubSlides($this, $this->lazio);
    $a = docgGroup($this->agent, [
        [$this->lazio->id => ['tier' => true]],
        [$this->lazio->id => ['seconds' => 60]],
    ]);
    docgApprove($this->admin, $a)->assertSessionHasNoErrors();
    $old = docgCurrent($a);
    $b = Proposal::query()->where('proposal_group_id', $a->proposal_group_id)->where('variant_letter', 'B')->sole();

    $this->actingAs($this->admin)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $b->id])->assertSessionHasNoErrors();
    expect(Document::query()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('documents.regenerate', $a->proposal_group_id))
        ->assertRedirect(route('proposals.groups.show', $a->proposal_group_id))
        ->assertSessionHas('toast.message', 'Documento in preparazione: sarà pronto tra poco');

    $new = docgCurrent($a);
    $old->refresh();

    expect($new->id)->not->toBe($old->id)
        ->and($new->status)->toBe(DocumentStatus::Ready)
        ->and($new->proposal_id)->toBe($b->id)
        ->and($new->generated_by)->toBe($this->admin->id)
        ->and(array_column($new->page_model['variants'], 'letter'))->toBe(['B'])
        ->and($old->superseded_at)->not->toBeNull()
        ->and($old->superseded_by_id)->toBe($new->id)
        ->and(soleAuditEvent(AuditAction::DocumentRegenerated)->old_values)->toMatchArray(['document_id' => $old->id]);

    // The recap of the new document has no title: one variant only.
    $recap = docgRenderer()->rendered[1]['pages'][1];
    expect($recap->data['title'])->toBeNull();

    $oldFile = StoredFile::query()->findOrFail($old->file_id);
    $newFile = StoredFile::query()->findOrFail($new->file_id);
    $this->actingAs($this->admin)->get(route('files.download', $oldFile))->assertOk();
    $this->actingAs($this->manager)->get(route('files.download', $oldFile))->assertForbidden();
    $this->actingAs($this->agent)->get(route('files.download', $oldFile))->assertForbidden();
    $this->actingAs($this->manager)->get(route('files.download', $newFile))->assertOk();

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.document.current.status', 'ready')
            ->where('group.document.canRegenerate', true)
            ->has('group.document.previous', 1)
            ->where('group.document.previous.0.statusLabel', 'Pronto'));
    $this->actingAs($this->manager)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->has('group.document.previous', 0));
});

it('D-090: the agent downloads the document of his own group, another agent cannot', function () {
    docgClubSlides($this, $this->lazio);
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);
    docgApprove($this->manager, $a)->assertSessionHasNoErrors();
    $file = StoredFile::query()->findOrFail(docgCurrent($a)->file_id);

    $this->actingAs($this->agent)->get(route('files.download', $file))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->otherAgent)->get(route('files.download', $file))->assertForbidden();

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.document.current.downloadUrl', route('files.download', $file))
            ->where('group.document.canRegenerate', false));
});

it('D-125: the manager cannot regenerate; a group not approved has no document to regenerate', function () {
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);

    $this->actingAs($this->manager)->post(route('documents.regenerate', $a->proposal_group_id))->assertForbidden();
    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $a->proposal_group_id))
        ->post(route('documents.regenerate', $a->proposal_group_id))
        ->assertRedirect(route('proposals.groups.show', $a->proposal_group_id))
        ->assertSessionHas('toast.message', 'Il documento si genera solo per un gruppo con almeno una variante approvata');

    expect(Document::query()->count())->toBe(0);
});

it('M09 §9: a rendering error is kept and retried; after the last attempt the document fails', function () {
    docgClubSlides($this, $this->lazio);
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);
    Queue::fake();
    docgApprove($this->admin, $a)->assertSessionHasNoErrors();
    Queue::assertPushedOn('documents', GenerateDocument::class);

    $document = docgCurrent($a);
    docgRenderer()->failWith = 'Chromium non risponde';

    expect(fn () => app(DocumentGenerator::class)->generate($document->id))->toThrow(RuntimeException::class, 'Chromium non risponde');

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Generating)
        ->and($document->attempts)->toBe(1)
        ->and($document->last_error)->toBe('Chromium non risponde');

    (new GenerateDocument($document->id))->failed(new RuntimeException('Chromium non risponde'));

    expect($document->refresh()->status)->toBe(DocumentStatus::Failed)
        ->and($document->last_error)->toBe('Generazione non riuscita dopo l\'ultimo tentativo: Chromium non risponde');
});

it('D-205: a job of a replaced document stores nothing', function () {
    docgClubSlides($this, $this->lazio);
    $a = docgGroup($this->agent, [[$this->lazio->id => ['tier' => true]]]);
    Queue::fake();
    docgApprove($this->admin, $a)->assertSessionHasNoErrors();
    $first = docgCurrent($a);

    app(RequestDocument::class)->handle($a->group()->firstOrFail(), $this->admin, regeneration: true);
    app(DocumentGenerator::class)->generate($first->id);

    expect($first->refresh()->status)->toBe(DocumentStatus::Queued)
        ->and($first->file_id)->toBeNull()
        ->and(StoredFile::query()->where('purpose', FilePurpose::GeneratedPdf->value)->count())->toBe(0);
});

it('D-252: the cover carries no logo when the client has none; a LED-only club recap has the LED row', function () {
    docgClubSlides($this, $this->lazio);
    $a = docgGroup($this->agent, [[$this->lazio->id => ['seconds' => 30]]]);
    Client::query()->whereKey($a->client_id)->update(['logo_file_id' => null]);

    docgApprove($this->admin, $a)->assertSessionHasNoErrors();

    $pages = docgRenderer()->rendered[0]['pages'];
    expect($pages[0]->data['logo'])->toBeNull()
        ->and($pages[1]->data['rows'][0]->label)->toBe('LED')
        ->and(docgCurrent($a)->status)->toBe(DocumentStatus::Ready);
});
