<?php

use App\Domain\Inventory\Services\InventoryAvailability;
use App\Domain\Proposals\Actions\DuplicateProposal;
use App\Enums\AuditAction;
use App\Enums\CommitmentReleaseReason;
use App\Enums\InventoryResource;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\Document;
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
use App\Models\ProposalEvent;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| New version of an approved variant (M07 FL-04, F-06, RB-07, CA7; D-041, D-096, D-198, D-216,
| D-464…D-468; slice F5, PR F5-04): the admin opens a working copy with "Modifica", changes it in the
| configurator in review mode and saves the version n+1; the availability is checked only on the
| difference (forceable), the commitments of version n are released as "superseded", the versions are
| listed in the detail of the group. The routes are in the route × role matrix (RouteMatrixTest); the
| concurrency in tests/Concurrency/SaveNewVersionTest.php.
|
| Data: 19 standard home matches (no calendar), 2nd row at 1.000,00 € per 1′ a match (1′ = 19.000,00 €),
| tier Top at 155.680,00 €; 10′ of 2nd row per match and one contract Top for the club.
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

    $this->lazio = Club::factory()->infront()->create(['name' => 'SS Lazio', 'code' => 'LAZIO']);
    ClubEnrollment::query()->create(['competition_season_id' => $this->league->id, 'club_id' => $this->lazio->id]);
    LedPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'led_format_id' => $this->format->id, 'unit_price' => '1000.00']);
    $this->rows = PoolCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'inventory_pool_id' => $this->pool->id, 'is_enabled' => true, 'seconds_per_match' => 600]);
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => $this->tier->id, 'season_price' => '155680.00']);
    PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => $this->tier->id, 'is_enabled' => true, 'quantity' => 1]);

    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create();
});

/** A group of one proposal of the club with the 2nd row (and the tier Top), sent and approved. */
function nvApproved(object $test, int $seconds = 60, bool $tier = true): Proposal
{
    $client = Client::factory()->ownedBy($test->agent)->create();
    test()->actingAs($test->agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    $version = $proposal->workingVersion;

    test()->actingAs($test->agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => [nvClub($test, $seconds, $tier)],
        ])
        ->assertSessionHasNoErrors();
    test()->actingAs($test->agent)->post(route('proposals.groups.submit', $proposal->proposal_group_id))->assertSessionHasNoErrors();
    test()->actingAs($test->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id))
        ->assertSessionHasNoErrors();

    return $proposal->refresh();
}

/**
 * @return array<string, mixed>
 */
function nvClub(object $test, int $seconds, bool $tier = true): array
{
    return [
        'club_id' => $test->lazio->id,
        'partnership_tier_id' => $tier ? $test->tier->id : null,
        'led_format_id' => $test->format->id,
        'led_seconds' => $seconds,
        'seats' => [],
    ];
}

function nvStart(User $actor, Proposal $proposal): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.revision.start', $proposal));
}

/** Automatic save of the working copy with the 2nd row at $seconds per match. */
function nvSave(object $test, Proposal $proposal, int $seconds, bool $tier = true): TestResponse
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    return test()->actingAs($test->admin)
        ->from(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.revision.update', $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => [nvClub($test, $seconds, $tier)],
        ]);
}

/**
 * @param  array<string, string>  $payload
 */
function nvVersion(User $actor, Proposal $proposal, array $payload = []): TestResponse
{
    $proposal->refresh();

    return test()->actingAs($actor)
        ->from(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->post(route('proposals.revision.save', $proposal), ['lock_version' => $proposal->lock_version, ...$payload]);
}

function nvPoolRemaining(object $test): int
{
    foreach (app(InventoryAvailability::class)->forClub($test->season, $test->lazio)['pool'] as $availability) {
        if ($availability->valueId === $test->pool->id) {
            return $availability->remaining();
        }
    }

    return 0;
}

it('M07-CA7: the admin changes 1′ into 2′: version 2, version 1 still there, inventory on the difference', function () {
    $proposal = nvApproved($this);
    /** @var ProposalVersion $first */
    $first = $proposal->currentVersion;
    expect(nvPoolRemaining($this))->toBe(540);

    nvStart($this->admin, $proposal)
        ->assertRedirect(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertSessionHas('toast.message', 'Modifica della proposta n. '.$proposal->number.': le modifiche si salvano come nuova versione');

    $this->actingAs($this->admin)->get(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Proposals/Configurator')
            ->where('step', 5)
            ->where('lastStep', 5)
            ->where('revision.versionNumber', 1)
            ->where('revision.nextNumber', 2)
            ->where('revision.changes', [])
            ->where('draft.saveUrl', route('proposals.revision.update', $proposal)));

    nvSave($this, $proposal, 120)->assertSessionHasNoErrors();

    // D-466: the agent still reads version 1 while the admin works.
    expect(nvPoolRemaining($this))->toBe(540)
        ->and($proposal->refresh()->current_version_id)->toBe($first->id);

    nvVersion($this->admin, $proposal)
        ->assertRedirect(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertSessionHas('toast.message', 'Proposta n. '.$proposal->number.': salvata la versione 2');

    $proposal->refresh();
    /** @var ProposalVersion $second */
    $second = $proposal->currentVersion;
    $commitments = InventoryCommitment::query()->where('proposal_id', $proposal->id)->orderBy('id')->get();

    expect($second->number)->toBe(2)
        ->and($second->isFrozen())->toBeTrue()
        ->and($second->list_total)->toBe('193680.00')
        ->and($second->frozen_by)->toBe($this->admin->id)
        ->and($proposal->workingVersion()->exists())->toBeFalse()
        ->and($proposal->status)->toBe(ProposalStatus::Approved)
        ->and(ProposalVersion::query()->whereKey($first->id)->value('list_total'))->toBe('174680.00')
        // D-192: the commitments of version 1 released as superseded, those of version 2 open.
        ->and($commitments->where('proposal_version_id', $first->id)->every(fn (InventoryCommitment $c): bool => $c->released_at !== null && $c->release_reason === CommitmentReleaseReason::Superseded))->toBeTrue()
        ->and($commitments->where('proposal_version_id', $second->id)->whereNull('released_at')->count())->toBe(2)
        ->and($commitments->where('proposal_version_id', $second->id)->firstWhere('resource_type', InventoryResource::Pool)?->quantity)->toBe(120)
        ->and(nvPoolRemaining($this))->toBe(480)
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->orderByDesc('id')->value('type'))->toBe(ProposalEventType::NewVersion)
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::NewVersion->value)->value('notes'))->toBe('Versione 2');

    $audit = auditEventsOf(AuditAction::ProposalNewVersion);
    expect($audit)->toHaveCount(1)
        ->and($audit[0]->old_values['version'] ?? null)->toBe(1)
        ->and($audit[0]->new_values['version'] ?? null)->toBe(2)
        ->and($audit[0]->old_values['list_total'] ?? null)->toBe('174680.00')
        ->and($audit[0]->new_values['list_total'] ?? null)->toBe('193680.00')
        ->and($audit[0]->new_values['club_LAZIO'] ?? null)->toBe('TOP | FILA_2 2′00″ | —')
        ->and(auditEventsOf(AuditAction::ProposalRevisionStarted))->toHaveCount(1);

    // D-448: the document of the group is asked again; the previous one is replaced.
    expect(Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->count())->toBe(2)
        ->and(Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->value('proposal_version_id'))->toBe($second->id);
});

it('M07 F-06, D-468: the detail lists the versions; admin sees the discount, the agent only the list', function () {
    $proposal = nvApproved($this);
    nvStart($this->admin, $proposal);
    nvSave($this, $proposal, 120)->assertSessionHasNoErrors();
    nvVersion($this->admin, $proposal, ['discount_type' => 'percent', 'discount_value' => '10'])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.variants.0.versions.0.number', 2)
            ->where('group.variants.0.versions.0.current', true)
            ->where('group.variants.0.versions.0.author', 'Anna Neri')
            ->where('group.variants.0.versions.0.listTotal', '193.680,00')
            ->where('group.variants.0.versions.0.discount', 'sconto 10,00%')
            ->where('group.variants.0.versions.0.finalTotal', '174.312,00')
            ->where('group.variants.0.versions.0.changes', [
                'SS Lazio: Top · 2ª fila 1′00″ → Top · 2ª fila 2′00″',
                'Totale di listino: € 174.680,00 → € 193.680,00',
                'Sconto: nessuno sconto → sconto 10,00%',
            ])
            ->where('group.variants.0.versions.1.number', 1)
            ->where('group.variants.0.versions.1.draft.listTotal', '174.680,00')
            ->where('group.variants.0.revisionStartUrl', route('proposals.revision.start', $proposal)));

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.variants.0.versions.0.discount', null)
            ->where('group.variants.0.versions.0.finalTotal', null)
            ->where('group.variants.0.versions.0.changes', [
                'SS Lazio: Top · 2ª fila 1′00″ → Top · 2ª fila 2′00″',
                'Totale di listino: € 174.680,00 → € 193.680,00',
            ])
            ->where('group.variants.0.revisionStartUrl', null));
});

it('D-465: an increase beyond the residual is kept in the copy, blocks the save and is forced by the admin', function () {
    $proposal = nvApproved($this);
    nvApproved($this, 480, false);
    expect(nvPoolRemaining($this))->toBe(60);

    nvStart($this->admin, $proposal);
    // The residual for the copy is 600 − 480 = 120″: 3′ are kept with their error (D-465).
    nvSave($this, $proposal, 180)->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('revision.forceable', true)
            ->where('revision.canForce', true)
            ->where('revision.forceNeeds.0.requested', '3′00″')
            ->where('revision.forceNeeds.0.remaining', '2′00″'));

    nvVersion($this->admin, $proposal)
        ->assertSessionHas('toast.message', fn (string $message): bool => str_starts_with($message, 'Approvazione bloccata'));
    expect($proposal->refresh()->currentVersion?->number)->toBe(1);

    $reason = 'Accordo del direttore commerciale con il club';
    nvVersion($this->admin, $proposal, ['forced_reason' => $reason])
        ->assertSessionHas('toast.message', 'Proposta n. '.$proposal->number.': salvata la versione 2 con forzatura');

    $version = $proposal->refresh()->currentVersion;
    $pool = InventoryCommitment::query()->where('proposal_version_id', $version?->id)->where('resource_type', InventoryResource::Pool->value)->first();

    expect($version?->is_forced)->toBeTrue()
        ->and($version?->forced_reason)->toBe($reason)
        ->and($pool?->is_forced)->toBeTrue()
        ->and(nvPoolRemaining($this))->toBe(-60)
        ->and(auditEventsOf(AuditAction::ProposalForced))->toHaveCount(1)
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::Forced->value)->value('notes'))->toBe($reason);
});

it('D-465: a quantity not increased never blocks, even with the capacity reduced under it', function () {
    $proposal = nvApproved($this);
    // The capacity falls to 30″ while version 1 commits 1′.
    $this->rows->update(['seconds_per_match' => 30]);

    nvStart($this->admin, $proposal);

    $this->actingAs($this->admin)->get(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->assertInertia(fn (Assert $page) => $page->where('revision.problems', []));

    nvVersion($this->admin, $proposal, ['discount_type' => 'amount', 'discount_value' => '4.680'])
        ->assertSessionHas('toast.message', 'Proposta n. '.$proposal->number.': salvata la versione 2');

    $version = $proposal->refresh()->currentVersion;

    expect($version?->number)->toBe(2)
        ->and($version?->is_forced)->toBeFalse()
        ->and($version?->final_total)->toBe('170000.00')
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::DiscountSet->value)->count())->toBe(1);
});

it('D-467: a version equal to the current one is refused', function () {
    $proposal = nvApproved($this);
    nvStart($this->admin, $proposal);

    nvVersion($this->admin, $proposal)
        ->assertSessionHas('toast.message', 'Nessuna modifica rispetto alla versione corrente: la nuova versione non è stata salvata');

    expect($proposal->refresh()->currentVersion?->number)->toBe(1)
        ->and($proposal->workingVersion()->exists())->toBeTrue()
        ->and(auditEventsOf(AuditAction::ProposalNewVersion))->toHaveCount(0);
});

it('D-466: "Annulla le modifiche" deletes the copy, the version and its commitments stay', function () {
    $proposal = nvApproved($this);
    nvStart($this->admin, $proposal);
    nvSave($this, $proposal, 120)->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->from(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->delete(route('proposals.revision.discard', $proposal))
        ->assertRedirect(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertSessionHas('toast.message', 'Modifiche della proposta n. '.$proposal->number.' annullate: resta la versione 1');

    expect($proposal->refresh()->workingVersion()->exists())->toBeFalse()
        ->and($proposal->currentVersion?->number)->toBe(1)
        ->and(InventoryCommitment::query()->where('proposal_id', $proposal->id)->whereNull('released_at')->count())->toBe(2)
        ->and(nvPoolRemaining($this))->toBe(540)
        ->and(auditEventsOf(AuditAction::ProposalRevisionDiscarded))->toHaveCount(1);

    // The configurator of a copy no longer there leads back to the group.
    $this->actingAs($this->admin)->get(route('proposals.revision.edit', $proposal))
        ->assertRedirect(route('proposals.groups.show', $proposal->proposal_group_id));
});

it('D-466: while the admin changes it, the agent duplicates the frozen version, not the copy', function () {
    $proposal = nvApproved($this);
    nvStart($this->admin, $proposal);
    nvSave($this, $proposal, 120)->assertSessionHasNoErrors();

    $client = Client::factory()->ownedBy($this->agent)->create();
    $copy = app(DuplicateProposal::class)->handle($proposal->refresh(), $this->agent, $client)->proposal;

    expect($copy->workingVersion?->clubs()->value('led_seconds_per_match'))->toBe(60);

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.variants.0.draft.listTotal', '174.680,00')
            ->where('group.variants.0.underRevision', false));
});

it('D-041, M07 RB-07: only the admin changes an approved proposal; a submitted one is not changed this way', function () {
    $proposal = nvApproved($this);

    nvStart($this->manager, $proposal)->assertForbidden();
    nvStart($this->agent, $proposal)->assertForbidden();

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.variants.0.revisionStartUrl', null));

    expect($proposal->refresh()->workingVersion()->exists())->toBeFalse();

    $client = Client::factory()->ownedBy($this->agent)->create();
    $this->actingAs($this->agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $draft = Proposal::query()->where('client_id', $client->id)->firstOrFail();

    nvStart($this->admin, $draft)->assertForbidden();
});
