<?php

use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\CommoditySector;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use App\Support\Navigation\PortalPages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Drafts of the configurator, steps 1 and 2 (M06 §4, §8, RB-01, RB-14; M05 F-02, FL-01, FL-02;
| D-165, D-366, D-368, D-372…D-375; slice F4-02). Permissions of the routes are also in the route ×
| role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    // Before the start of 2027-28: the default start is the start of the season (D-375).
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
});

function draftsSeason(int $year = 2027): Season
{
    return Season::query()->where('code', sprintf('%d-%02d', $year, ($year + 1) % 100))->first()
        ?? Season::factory()->forYear($year)->create();
}

/**
 * A draft of the agent made through the configurator, on the season of the top bar (2027-28 unless
 * the test chose another one).
 */
function draftsCreate(User $agent, ?Client $client = null): Proposal
{
    draftsSeason();
    $client ??= Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);

    return Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function draftsPayload(Proposal $proposal, array $overrides = []): array
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    return array_merge([
        'lock_version' => $proposal->lock_version,
        'client_id' => $proposal->client_id,
        'season_id' => $proposal->season_id,
        'starts_on' => $version?->starts_on?->toDateString(),
        'ends_on' => $version?->ends_on?->toDateString(),
    ], $overrides);
}

function draftsLogo(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.png', SampleFiles::png(400, 200));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function draftsClientForm(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Bianchi Srl',
        'vat_number' => '',
        'email' => 'info@bianchi.example',
        'contact_name' => '',
        'commodity_sector_id' => (string) CommoditySector::factory()->create()->id,
        'sector_free_text' => '',
        'logo' => draftsLogo(),
    ], $overrides);
}

it('D-375: "Nuova proposta" opens step 1 without creating an empty draft', function () {
    draftsSeason();
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get(PortalPages::url('proposals_new'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Proposals/Configurator')
            ->where('draft', null)
            ->where('step', 1)
            ->where('lastStep', 5)
            ->where('createUrl', route('proposals.store')));

    expect(Proposal::query()->count())->toBe(0);
});

it('M05-CA1, F-02, D-366, D-368: step 1 suggests only the active, not merged clients of the agent', function () {
    $agentA = User::factory()->agent()->create();
    $agentB = User::factory()->agent()->create();
    Client::factory()->ownedBy($agentA)->named('Alfa Spa')->create();
    $kept = Client::factory()->ownedBy($agentB)->named('Beta Srl')->create();
    Client::factory()->ownedBy($agentB)->named('Gamma Srl')->create(['is_active' => false]);
    Client::factory()->ownedBy($agentB)->named('Delta Srl')->create(['is_active' => false, 'merged_into_id' => $kept->id]);

    $this->actingAs($agentB)
        ->get(PortalPages::url('proposals_new'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients', 1)
            ->where('clients.0.label', 'Beta Srl')
            ->where('clients.0.value', (string) $kept->id));
});

it('D-371, D-375: choosing the client creates the draft with number, defaults of the period, history and audit', function () {
    $season = draftsSeason();
    $agent = User::factory()->agent()->create();
    $client = Client::factory()->ownedBy($agent)->named('Rossi Srl')->create();

    $response = $this->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);

    $proposal = Proposal::query()->sole();
    $response->assertRedirect(route('proposals.edit', ['proposal' => $proposal, 'passo' => 2]));

    $version = $proposal->workingVersion;
    expect($proposal->number)->toBeGreaterThanOrEqual(204)
        ->and($proposal->status)->toBe(ProposalStatus::Draft)
        ->and($proposal->agent_id)->toBe($agent->id)
        ->and($proposal->client_id)->toBe($client->id)
        ->and($proposal->season_id)->toBe($season->id)
        ->and($proposal->lock_version)->toBe(0)
        ->and($version?->starts_on?->toDateString())->toBe('2027-07-01')
        ->and($version?->ends_on?->toDateString())->toBe('2028-06-30');

    $event = ProposalEvent::query()->sole();
    expect($event->type)->toBe(ProposalEventType::Created)
        ->and($event->user_id)->toBe($agent->id)
        ->and($event->proposal_version_id)->toBe($version?->id);

    $audit = soleAuditEvent(AuditAction::ProposalDraftCreated);
    expect($audit->object_type)->toBe('proposal')
        ->and($audit->object_label)->toBe('Proposta n. '.$proposal->number)
        ->and($audit->new_values)->toMatchArray([
            'number' => $proposal->number,
            'client_name' => 'Rossi Srl',
            'season_code' => '2027-28',
            'starts_on' => '2027-07-01',
            'ends_on' => '2028-06-30',
        ]);
});

it('D-375: with the season already started the default start is today', function () {
    draftsSeason()->update(['status' => 'open']);
    $this->travelTo(Carbon::parse('2027-10-15 09:00', 'Europe/Rome'));

    $proposal = draftsCreate(User::factory()->agent()->create());

    expect($proposal->workingVersion?->starts_on?->toDateString())->toBe('2027-10-15')
        ->and($proposal->workingVersion?->ends_on?->toDateString())->toBe('2028-06-30');
});

it('D-375, D-317: the draft starts on the season of the top bar, never on a closed one', function () {
    $closed = Season::factory()->forYear(2026)->closed()->create();
    $next = draftsSeason(2027);
    $later = draftsSeason(2028);
    $agent = User::factory()->agent()->create();

    $proposal = draftsCreate($agent);
    expect($proposal->season_id)->toBe($next->id);

    // The season chosen in the top bar…
    $this->actingAs($agent)->post(route('season.select'), ['season_id' => $later->id]);
    expect(session(CurrentSeason::SESSION_KEY))->toBe($later->id)
        ->and(draftsCreate($agent)->season_id)->toBe($later->id);

    // …unless it is closed: then the first season still running or to come.
    $this->actingAs($agent)->post(route('season.select'), ['season_id' => $closed->id]);
    expect(draftsCreate($agent)->season_id)->toBe($next->id);
});

it('M05 F-02, RB-14: a client of another agent, deactivated or merged is refused and nothing is created', function (string $case) {
    draftsSeason();
    $agent = User::factory()->agent()->create();
    $client = match ($case) {
        'other agent' => Client::factory()->create(),
        'inactive' => Client::factory()->ownedBy($agent)->create(['is_active' => false]),
        'merged' => Client::factory()->ownedBy($agent)->create([
            'is_active' => false,
            'merged_into_id' => Client::factory()->ownedBy($agent)->create()->id,
        ]),
        default => throw new InvalidArgumentException($case),
    };

    $this->actingAs($agent)
        ->from(PortalPages::url('proposals_new'))
        ->post(route('proposals.store'), ['client_id' => $client->id])
        ->assertRedirect(PortalPages::url('proposals_new'))
        ->assertSessionHas('toast.message', 'Scegli uno dei tuoi clienti attivi');

    expect(Proposal::query()->count())->toBe(0)
        ->and(auditEventsOf(AuditAction::ProposalDraftCreated))->toHaveCount(0);
})->with(['other agent', 'inactive', 'merged']);

it('D-372: an automatic save writes the changed fields only and moves the lock version', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);
    $edit = route('proposals.edit', ['proposal' => $proposal, 'passo' => 2]);

    $this->actingAs($agent)->from($edit)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal, ['starts_on' => '2027-09-01']))
        ->assertRedirect($edit)
        ->assertSessionHasNoErrors();

    $proposal->refresh();
    expect($proposal->lock_version)->toBe(1)
        ->and($proposal->workingVersion?->starts_on?->toDateString())->toBe('2027-09-01');

    $audit = soleAuditEvent(AuditAction::ProposalDraftUpdated);
    expect($audit->old_values)->toBe(['starts_on' => '2027-07-01'])
        ->and($audit->new_values)->toBe(['starts_on' => '2027-09-01']);

    // Nothing changed (e.g. the save at a change of step): no event, same lock version.
    $this->actingAs($agent)->from($edit)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal))
        ->assertSessionHasNoErrors();

    expect($proposal->refresh()->lock_version)->toBe(1)
        ->and(auditEventsOf(AuditAction::ProposalDraftUpdated))->toHaveCount(1);
});

it('D-372: changing client and season writes both, with the defaults of the new period chosen by the page', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);
    $client = Client::factory()->ownedBy($agent)->named('Verdi Spa')->create();
    $season = draftsSeason(2028);

    $this->actingAs($agent)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal, [
            'client_id' => $client->id,
            'season_id' => $season->id,
            'starts_on' => '2028-07-01',
            'ends_on' => '2029-06-30',
        ]))
        ->assertSessionHasNoErrors();

    $audit = soleAuditEvent(AuditAction::ProposalDraftUpdated);
    expect($audit->new_values)->toBe([
        'client_id' => $client->id,
        'client_name' => 'Verdi Spa',
        'season_id' => $season->id,
        'season_code' => '2028-29',
        'starts_on' => '2028-07-01',
        'ends_on' => '2029-06-30',
    ]);
});

it('D-165, D-216: a save with an old lock version is refused and writes nothing', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);
    $stale = draftsPayload($proposal);

    $this->actingAs($agent)->put(route('proposals.update', $proposal), draftsPayload($proposal, ['ends_on' => '2028-05-31']));

    $this->actingAs($agent)
        ->put(route('proposals.update', $proposal), array_merge($stale, ['starts_on' => '2027-08-01']))
        ->assertSessionHasErrors(['conflict' => __('proposals.validation.conflict')]);

    $proposal->refresh();
    expect($proposal->lock_version)->toBe(1)
        ->and($proposal->workingVersion?->starts_on?->toDateString())->toBe('2027-07-01')
        ->and($proposal->workingVersion?->ends_on?->toDateString())->toBe('2028-05-31');
});

it('M06 §8, RB-01: the period must stay inside the season, with the end not before the start', function (array $period, string $field, string $message) {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);

    $this->actingAs($agent)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal, $period))
        ->assertSessionHasErrors([$field => $message]);

    expect($proposal->refresh()->lock_version)->toBe(0)
        ->and(auditEventsOf(AuditAction::ProposalDraftUpdated))->toHaveCount(0);
})->with([
    'inizio prima della stagione' => [['starts_on' => '2027-06-30'], 'starts_on', 'La data di inizio deve essere nella stagione, dal 01/07/2027 al 30/06/2028.'],
    'inizio dopo la stagione' => [['starts_on' => '2028-07-01', 'ends_on' => '2028-07-02'], 'starts_on', 'La data di inizio deve essere nella stagione, dal 01/07/2027 al 30/06/2028.'],
    'fine oltre la stagione' => [['ends_on' => '2028-07-01'], 'ends_on', 'La data di fine non può superare la fine della stagione (30/06/2028).'],
    'fine prima dell\'inizio' => [['starts_on' => '2027-10-01', 'ends_on' => '2027-09-30'], 'ends_on', 'La data di fine non può essere precedente alla data di inizio.'],
    'inizio mancante' => [['starts_on' => null], 'starts_on', 'Indica la data di inizio.'],
    'fine mancante' => [['ends_on' => null], 'ends_on', 'Indica la data di fine.'],
]);

it('D-317, D-366: a closed season or a client the agent cannot choose is refused at the save', function (string $case) {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);
    $overrides = match ($case) {
        'closed season' => ['season_id' => Season::factory()->forYear(2029)->closed()->create()->id, 'starts_on' => '2029-07-01', 'ends_on' => '2030-06-30'],
        'other client' => ['client_id' => Client::factory()->create()->id],
        'inactive client' => ['client_id' => Client::factory()->ownedBy($agent)->create(['is_active' => false])->id],
        default => throw new InvalidArgumentException($case),
    };

    $this->actingAs($agent)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal, $overrides))
        ->assertSessionHas('toast.type', 'error');

    expect($proposal->refresh()->lock_version)->toBe(0);
})->with(['closed season', 'other client', 'inactive client']);

it('D-366: the client of the draft deactivated later stays on it, with a note', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);
    $proposal->client?->update(['is_active' => false]);

    $this->actingAs($agent)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal, ['ends_on' => '2028-05-31']))
        ->assertSessionHasNoErrors();

    $this->actingAs($agent)
        ->get(route('proposals.edit', $proposal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.client.selectable', false)
            ->has('clients', 0));
});

it('M07 RB-08: SaveDraft refuses a proposal that is no longer a draft', function () {
    $agent = User::factory()->agent()->create();
    $proposal = Proposal::factory()->ownedBy($agent)->withWorkingVersion()->status(ProposalStatus::Submitted)->create();

    app(SaveDraft::class)->handle($proposal, new DraftData((int) $proposal->client_id, null, null, null), 0, $agent);
})->throws(ProposalsActionNotAllowed::class);

it('D-375: the draft opens at the step of the address, steps not built yet go back to the last one', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);

    $this->actingAs($agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Proposals/Configurator')
            ->where('step', 2)
            ->where('draft.number', $proposal->number)
            ->where('draft.lockVersion', 0)
            ->where('draft.startsOn', '2027-07-01')
            ->where('draft.endsOn', '2028-06-30')
            ->where('draft.listTotal', '0,00')
            ->where('draft.saveUrl', route('proposals.update', $proposal))
            ->has('seasons', 1)
            ->where('seasons.0.defaultStartsOn', '2027-07-01'));

    $this->actingAs($agent)
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->assertInertia(fn (Assert $page) => $page->where('step', 4));
});

it('RB-14: the agent opens, saves and deletes only his own drafts', function () {
    $proposal = draftsCreate(User::factory()->agent()->create());
    $other = User::factory()->agent()->create();

    $this->actingAs($other)->get(route('proposals.edit', $proposal))->assertForbidden();
    $this->actingAs($other)->put(route('proposals.update', $proposal), draftsPayload($proposal, ['ends_on' => '2028-05-31']))->assertForbidden();
    $this->actingAs($other)->delete(route('proposals.destroy', $proposal))->assertForbidden();

    expect(Proposal::query()->whereKey($proposal->id)->exists())->toBeTrue()
        ->and($proposal->refresh()->lock_version)->toBe(0);
});

it('D-374, D-411: "Le mie proposte" lists the proposals of the agent, the most recent first', function () {
    $agent = User::factory()->agent()->create();
    $first = draftsCreate($agent, Client::factory()->ownedBy($agent)->named('Prima Srl')->create());
    $this->travel(5)->minutes();
    $second = draftsCreate($agent, Client::factory()->ownedBy($agent)->named('Seconda Srl')->create());
    draftsCreate(User::factory()->agent()->create());
    $this->travel(5)->minutes();
    $submitted = Proposal::factory()->ownedBy($agent)->withWorkingVersion()->status(ProposalStatus::Submitted)->create();

    $this->actingAs($agent)
        ->get(PortalPages::url('proposals_mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Proposals/Mine')
            ->has('drafts', 3)
            ->where('drafts.0.number', $submitted->number)
            ->where('drafts.0.statusLabel', 'In approvazione')
            ->where('drafts.0.editable', false)
            ->where('drafts.0.editUrl', null)
            ->where('drafts.0.groupUrl', route('proposals.groups.show', $submitted->proposal_group_id))
            ->where('drafts.1.number', $second->number)
            ->where('drafts.1.client', 'Seconda Srl')
            ->where('drafts.1.season', '2027-28')
            ->where('drafts.1.period', 'Dal 01/07/2027 al 30/06/2028')
            ->where('drafts.1.statusLabel', 'Bozza')
            ->where('drafts.1.editUrl', route('proposals.edit', $second))
            ->where('drafts.2.number', $first->number));
});

it('M07 RB-08, D-372: the agent deletes a draft with its version and history; the number is traced', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);
    $number = $proposal->number;

    $this->actingAs($agent)
        ->delete(route('proposals.destroy', $proposal))
        ->assertRedirect(PortalPages::url('proposals_mine'))
        ->assertSessionHas('toast.message', 'Bozza n. '.$number.' eliminata');

    expect(Proposal::query()->count())->toBe(0)
        ->and(ProposalVersion::query()->count())->toBe(0)
        ->and(ProposalEvent::query()->count())->toBe(0);

    $audit = soleAuditEvent(AuditAction::ProposalDraftDeleted);
    expect($audit->object_label)->toBe('Proposta n. '.$number)
        ->and($audit->old_values)->toMatchArray(['number' => $number, 'season_code' => '2027-28'])
        ->and($audit->new_values)->toBeNull();
});

it('FL-02: a client created at step 1 belongs to the agent and starts the draft', function () {
    draftsSeason();
    $agent = User::factory()->agent()->create();

    $response = $this->actingAs($agent)->post(route('proposals.clients.store'), draftsClientForm());

    $client = Client::query()->sole();
    $proposal = Proposal::query()->sole();
    $response->assertRedirect(route('proposals.edit', ['proposal' => $proposal, 'passo' => 2]))
        ->assertSessionHas('toast.message', 'Cliente Bianchi Srl creato e scelto per la proposta');

    expect($client->owner_id)->toBe($agent->id)
        ->and($proposal->client_id)->toBe($client->id);
    soleAuditEvent(AuditAction::ClientCreated);
    soleAuditEvent(AuditAction::ProposalDraftCreated);
});

it('M05-CA2, FL-02: a duplicate created at step 1 is blocked with the neutral message and no draft is made', function () {
    draftsSeason();
    Client::factory()->ownedBy(User::factory()->agent()->create())->named('ADECCO')->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->from(PortalPages::url('proposals_new'))
        ->post(route('proposals.clients.store'), draftsClientForm(['company_name' => 'Adecco S.p.A.']))
        ->assertRedirect(PortalPages::url('proposals_new'))
        ->assertSessionHasErrors(['duplicate' => 'Cliente già presente in anagrafica: contatta l\'amministratore']);

    expect(Client::query()->count())->toBe(1)
        ->and(Proposal::query()->count())->toBe(0);
});

it('FL-02: a client created on a draft replaces the client of the draft', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);

    $this->actingAs($agent)
        ->post(route('proposals.clients.update', $proposal), draftsClientForm(['lock_version' => 0]))
        ->assertSessionHasNoErrors();

    $client = Client::query()->where('company_name', 'Bianchi Srl')->sole();
    $proposal->refresh();
    expect($proposal->client_id)->toBe($client->id)
        ->and($proposal->lock_version)->toBe(1)
        ->and(soleAuditEvent(AuditAction::ProposalDraftUpdated)->new_values)->toBe([
            'client_id' => $client->id,
            'client_name' => 'Bianchi Srl',
        ]);
});

it('D-375: after a session expired during an automatic save the login goes back to the draft', function () {
    $agent = User::factory()->agent()->withPassword('Una-password-di-prova-2027')->create();
    $proposal = draftsCreate($agent);
    $edit = route('proposals.edit', ['proposal' => $proposal, 'passo' => 2]);

    $this->actingAs($agent)->get($edit)->assertOk();
    $this->travel(481)->minutes();

    $this->from($edit)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal, ['ends_on' => '2028-05-31']))
        ->assertRedirect(route('login'));
    $this->assertGuest();
    expect($proposal->refresh()->lock_version)->toBe(0);

    $this->post(route('login.store'), ['email' => $agent->email, 'password' => 'Una-password-di-prova-2027'])
        ->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login.store'), ['code' => totpCode()])
        ->assertRedirect($edit);
});

it('D-375: after an expired session only a page of the portal is reopened, never another site', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);

    $this->actingAs($agent)->get(route('proposals.edit', $proposal))->assertOk();
    $this->travel(481)->minutes();

    $this->from('https://esempio.example/pagina')
        ->put(route('proposals.update', $proposal), draftsPayload($proposal))
        ->assertRedirect(route('login'))
        ->assertSessionMissing('url.intended');
});

it('M06 §8, D-381: a season already chosen is never removed by a save', function () {
    $agent = User::factory()->agent()->create();
    $proposal = draftsCreate($agent);
    $seasonId = $proposal->season_id;

    $this->actingAs($agent)
        ->put(route('proposals.update', $proposal), draftsPayload($proposal, ['season_id' => '', 'starts_on' => '', 'ends_on' => '']))
        ->assertSessionHasErrors(['season_id' => 'Scegli la stagione.']);

    expect($proposal->refresh()->season_id)->toBe($seasonId)
        ->and($proposal->lock_version)->toBe(0)
        ->and($proposal->workingVersion?->starts_on?->toDateString())->toBe('2027-07-01')
        ->and(auditEventsOf(AuditAction::ProposalDraftUpdated))->toHaveCount(0);
});
