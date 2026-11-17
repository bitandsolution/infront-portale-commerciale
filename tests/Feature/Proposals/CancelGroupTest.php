<?php

use App\Domain\Inventory\Services\InventoryAvailability;
use App\Domain\Proposals\Actions\CancelGroup;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
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
use App\Models\ProposalGroup;
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
| Cancellation of an approved proposal (M07 F-07, FL-05, RB-01, CA8; D-031, D-095, D-444, D-447,
| D-469…D-473; slice F5, PR F5-05): only the admin, with a reason; the approved variants become
| "Annullata", their commitments are released as "cancelled", a new version in progress is discarded,
| the PDF stays downloadable and is not regenerated, the cancellation is final. The route is in the
| route × role matrix (RouteMatrixTest); the concurrency in tests/Concurrency/CancelGroupTest.php.
|
| Data: 19 standard home matches, 2nd row at 1.000,00 € per 1′ a match, tier Top at 155.680,00 € with
| two contracts; 10′ of 2nd row per match for the club.
*/

const CANCEL_REASON = 'Il cliente ha ritirato il budget della stagione';

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
    PoolCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'inventory_pool_id' => $this->pool->id, 'is_enabled' => true, 'seconds_per_match' => 600]);
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => $this->tier->id, 'season_price' => '155680.00']);
    PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => $this->tier->id, 'is_enabled' => true, 'quantity' => 2]);

    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create();
});

/** Saves the club of a draft (or of a working copy) as the configurator does. */
function cgSave(User $actor, Proposal $proposal, int $seconds, string $route = 'proposals.update', string $page = 'proposals.edit'): void
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    test()->actingAs($actor)
        ->from(route($page, ['proposal' => $proposal, 'passo' => 4]))
        ->put(route($route, $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => [[
                'club_id' => test()->lazio->id,
                'partnership_tier_id' => test()->tier->id,
                'led_format_id' => test()->format->id,
                'led_seconds' => $seconds,
                'seats' => [],
            ]],
        ])
        ->assertSessionHasNoErrors();
}

/**
 * A group approved by the admin: one variant per entry of $seconds (A, B…), every one with the tier Top.
 *
 * @param  list<int>  $seconds
 */
function cgApproved(object $test, array $seconds = [60]): Proposal
{
    $client = Client::factory()->ownedBy($test->agent)->create();
    test()->actingAs($test->agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $first = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    cgSave($test->agent, $first, $seconds[0]);

    foreach (array_slice($seconds, 1) as $variantSeconds) {
        test()->actingAs($test->agent)
            ->from(route('proposals.edit', ['proposal' => $first, 'passo' => 5]))
            ->post(route('proposals.variants.store', $first), ['step' => 5])
            ->assertSessionHasNoErrors();
        cgSave($test->agent, Proposal::query()->where('proposal_group_id', $first->proposal_group_id)->latest('id')->firstOrFail(), $variantSeconds);
    }

    test()->actingAs($test->agent)->post(route('proposals.groups.submit', $first->proposal_group_id))->assertSessionHasNoErrors();
    test()->actingAs($test->admin)
        ->from(route('proposals.groups.show', $first->proposal_group_id))
        ->post(route('proposals.groups.approve', $first->proposal_group_id))
        ->assertSessionHasNoErrors();

    return $first->refresh();
}

function cgCancel(User $actor, Proposal $proposal, string $reason = CANCEL_REASON): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.cancel', $proposal->proposal_group_id), ['cancel_reason' => $reason]);
}

function cgRemaining(object $test, InventoryResource $resource, int $valueId): int
{
    foreach (app(InventoryAvailability::class)->forClub($test->season, $test->lazio)[$resource->value] as $availability) {
        if ($availability->valueId === $valueId) {
            return $availability->remaining();
        }
    }

    return -1;
}

it('M07-CA8: the admin cancels an approved proposal: commitments released, status, history and audit', function () {
    $proposal = cgApproved($this);
    expect(cgRemaining($this, InventoryResource::Pool, $this->pool->id))->toBe(540)
        ->and(cgRemaining($this, InventoryResource::Partnership, $this->tier->id))->toBe(1);

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.canCancel', true)
            ->where('group.cancelDiscardsRevision', false)
            ->where('group.cancellation', null));

    cgCancel($this->admin, $proposal, '  '.CANCEL_REASON.'  ')
        ->assertRedirect(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertSessionHas('toast.message', 'Proposta n. '.$proposal->number.' annullata: disponibilità rilasciata');

    $proposal->refresh();
    $commitments = InventoryCommitment::query()->where('proposal_id', $proposal->id)->get();

    expect($proposal->status)->toBe(ProposalStatus::Cancelled)
        ->and($proposal->cancelled_by)->toBe($this->admin->id)
        ->and($proposal->cancel_reason)->toBe(CANCEL_REASON)
        ->and($proposal->cancelled_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($commitments)->toHaveCount(2)
        ->and($commitments->every(fn (InventoryCommitment $commitment): bool => $commitment->released_at !== null && $commitment->release_reason === CommitmentReleaseReason::Cancelled))->toBeTrue()
        ->and(cgRemaining($this, InventoryResource::Pool, $this->pool->id))->toBe(600)
        ->and(cgRemaining($this, InventoryResource::Partnership, $this->tier->id))->toBe(2)
        // D-198: the frozen version stays as it was.
        ->and($proposal->currentVersion?->number)->toBe(1)
        ->and($proposal->currentVersion?->isFrozen())->toBeTrue();

    $event = ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::Cancelled->value)->sole();
    expect($event->notes)->toBe(CANCEL_REASON)
        ->and($event->user_id)->toBe($this->admin->id);

    $audit = auditEventsOf(AuditAction::ProposalCancelled)->sole();
    expect($audit->old_values)->toBe(['status' => 'approved'])
        ->and($audit->new_values)->toMatchArray([
            'status' => 'cancelled',
            'variant_letter' => 'A',
            'version' => 1,
            'cancel_reason' => CANCEL_REASON,
            'released_commitments' => 2,
            'discarded_revision' => false,
        ]);
});

it('M07-CA8: the group page shows the cancellation to admin, manager and agent (D-472)', function () {
    $proposal = cgApproved($this);
    cgCancel($this->admin, $proposal)->assertSessionHasNoErrors();

    foreach ([$this->admin, $this->manager, $this->agent] as $viewer) {
        $this->actingAs($viewer)->get(route('proposals.groups.show', $proposal->proposal_group_id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proposals/Group')
                ->where('group.status', 'cancelled')
                ->where('group.canCancel', false)
                ->where('group.canChoose', false)
                ->where('group.cancellation.by', 'Anna Neri')
                ->where('group.cancellation.reason', CANCEL_REASON)
                ->where('group.variants.0.versions.0.number', 1));
    }

    $this->actingAs($this->agent)->get(route('pages.proposals_mine'))
        ->assertInertia(fn (Assert $page) => $page->where('drafts.0.statusLabel', 'Annullata'));
    $this->actingAs($this->admin)->get(route('pages.proposals_all', ['stato' => 'cancelled']))
        ->assertInertia(fn (Assert $page) => $page->where('groups.0.number', $proposal->number));
});

it('D-469: the cancellation discards the new version in progress with its rows', function () {
    $proposal = cgApproved($this);
    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.revision.start', $proposal))
        ->assertSessionHasNoErrors();
    cgSave($this->admin, $proposal, 120, 'proposals.revision.update', 'proposals.revision.edit');
    $working = $proposal->refresh()->workingVersion;
    expect($working)->toBeInstanceOf(ProposalVersion::class);

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.cancelDiscardsRevision', true));

    cgCancel($this->admin, $proposal)->assertSessionHasNoErrors();

    expect(ProposalVersion::query()->whereKey($working?->id)->exists())->toBeFalse()
        ->and(ProposalVersion::query()->where('proposal_id', $proposal->id)->count())->toBe(1)
        ->and($proposal->refresh()->isUnderRevision())->toBeFalse()
        ->and(auditEventsOf(AuditAction::ProposalCancelled)->sole()->new_values['discarded_revision'] ?? null)->toBeTrue();
});

it('D-470: the PDF stays downloadable, flagged as cancelled, and is not regenerated', function () {
    $proposal = cgApproved($this);
    $documents = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->count();

    cgCancel($this->admin, $proposal)->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('documents.regenerate', $proposal->proposal_group_id))
        ->assertSessionHas('toast.message', 'Il documento si genera solo per un gruppo con almeno una variante approvata');

    expect(Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->count())->toBe($documents);

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.document.cancelled', true)
            ->where('group.document.canRegenerate', false));
});

it('D-471: the cancellation is final: no new version, no choice, no second cancellation', function () {
    $proposal = cgApproved($this);
    cgCancel($this->admin, $proposal)->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.revision.start', $proposal))
        ->assertForbidden();
    cgCancel($this->admin, $proposal)
        ->assertSessionHas('toast.message', 'La proposta non ha varianti approvate: non si può annullare');

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Cancelled)
        ->and($proposal->workingVersion)->toBeNull()
        ->and(auditEventsOf(AuditAction::ProposalCancelled))->toHaveCount(1);
});

it('D-473: after the choice of the client only the chosen variant is cancelled; the group is cancelled', function () {
    $a = cgApproved($this, [60, 120]);
    $b = Proposal::query()->where('proposal_group_id', $a->proposal_group_id)->where('variant_letter', 'B')->firstOrFail();

    $this->actingAs($this->admin)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $b->id])->assertSessionHasNoErrors();
    cgCancel($this->admin, $a)->assertSessionHasNoErrors();

    expect($a->refresh()->status)->toBe(ProposalStatus::NotChosen)
        ->and($a->cancel_reason)->toBeNull()
        ->and($b->refresh()->status)->toBe(ProposalStatus::Cancelled)
        ->and(InventoryCommitment::query()->where('proposal_id', $a->id)->pluck('release_reason')->unique()->all())->toBe([CommitmentReleaseReason::NotChosen])
        ->and(InventoryCommitment::query()->where('proposal_id', $b->id)->pluck('release_reason')->unique()->all())->toBe([CommitmentReleaseReason::Cancelled])
        ->and(auditEventsOf(AuditAction::ProposalCancelled))->toHaveCount(1);

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.status', 'cancelled'));
});

it('D-473: without a choice every approved variant of the group is cancelled', function () {
    $a = cgApproved($this, [60, 120]);

    cgCancel($this->admin, $a)->assertSessionHasNoErrors();

    expect(Proposal::query()->where('proposal_group_id', $a->proposal_group_id)->pluck('status')->unique()->all())->toBe([ProposalStatus::Cancelled])
        ->and(InventoryCommitment::query()->whereNull('released_at')->count())->toBe(0)
        ->and(auditEventsOf(AuditAction::ProposalCancelled))->toHaveCount(2);
});

it('M07 RB-01: only the admin cancels, and only with a reason of at least 10 characters', function () {
    $proposal = cgApproved($this);

    cgCancel($this->manager, $proposal)->assertForbidden();
    cgCancel($this->agent, $proposal)->assertForbidden();
    cgCancel($this->admin, $proposal, 'Troppo')
        ->assertSessionHasErrors(['cancel_reason' => 'Scrivi il motivo dell\'annullamento: almeno 10 caratteri']);
    cgCancel($this->admin, $proposal, '')->assertSessionHasErrors('cancel_reason');

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.canCancel', false));

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and(InventoryCommitment::query()->whereNull('released_at')->count())->toBe(2);

    $group = ProposalGroup::query()->findOrFail($proposal->proposal_group_id);
    expect(fn () => app(CancelGroup::class)->handle($group, CANCEL_REASON, $this->manager))
        ->toThrow(ProposalsActionNotAllowed::class, 'Solo l\'amministratore può annullare una proposta approvata')
        ->and(fn () => app(CancelGroup::class)->handle($group, 'Breve', $this->admin))
        ->toThrow(ProposalsActionNotAllowed::class, 'almeno 10 caratteri');
});

it('M07 RB-01: a group not approved (in approval) cannot be cancelled', function () {
    $client = Client::factory()->ownedBy($this->agent)->create();
    $this->actingAs($this->agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    cgSave($this->agent, $proposal, 60);
    $this->actingAs($this->agent)->post(route('proposals.groups.submit', $proposal->proposal_group_id))->assertSessionHasNoErrors();

    cgCancel($this->admin, $proposal)
        ->assertSessionHas('toast.message', 'La proposta non ha varianti approvate: non si può annullare');

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Submitted);
});

it('D-091: the agent can duplicate a cancelled proposal into a new draft', function () {
    $proposal = cgApproved($this);
    cgCancel($this->admin, $proposal)->assertSessionHasNoErrors();

    $this->actingAs($this->agent)
        ->from(route('pages.proposals_mine'))
        ->post(route('proposals.duplicate', $proposal), ['client_id' => $proposal->client_id])
        ->assertSessionHasNoErrors();

    expect(Proposal::query()->where('duplicated_from_id', $proposal->id)->sole()->status)->toBe(ProposalStatus::Draft);
});
