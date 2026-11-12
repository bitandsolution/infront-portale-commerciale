<?php

use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
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
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Sending for approval and rejection of a group of variants (M07 §4, F-01, FL-03; D-395, D-411,
| D-412; slice DM, PR DM-03a): the agent sends every variant together after a new check on list and
| residuals; admin and manager see the queue "Da approvare" and the detail of the group and reject it
| with an optional reason; the agent corrects the rejected group and sends it again. The routes are in
| the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);

    $this->lazio = revClub($this, 'SS Lazio', 'LAZIO');
    $this->agent = User::factory()->agent()->create(['first_name' => 'Mario', 'last_name' => 'Verdi']);
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create();
});

function revClub(object $test, string $name, string $code): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, 'code' => $code]);
    ClubEnrollment::query()->create(['competition_season_id' => $test->league->id, 'club_id' => $club->id]);
    $format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();
    $tier = PartnershipTier::query()->where('code', 'TOP')->firstOrFail();

    LedPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'led_format_id' => $format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create([
        'season_id' => $test->season->id,
        'club_id' => $club->id,
        'inventory_pool_id' => InventoryPool::query()->where('code', 'FILA_2')->firstOrFail()->id,
        'is_enabled' => true,
        'seconds_per_match' => 600,
    ]);
    PartnershipPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'partnership_tier_id' => $tier->id, 'season_price' => '180000.00']);
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => $tier->id, 'is_enabled' => true, 'quantity' => 2]);

    return $club;
}

/** Saves the clubs of a draft as the configurator does (Lazio TOP and 1′ of 2nd row by default). */
function revSave(User $agent, Proposal $proposal, ?Club $club, int $seconds = 60): TestResponse
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    return test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => $club === null ? [] : [[
                'club_id' => $club->id,
                'partnership_tier_id' => PartnershipTier::query()->where('code', 'TOP')->value('id'),
                'led_format_id' => LedFormat::query()->where('code', 'FILA_2')->value('id'),
                'led_seconds' => $seconds,
                'seats' => [],
            ]],
        ]);
}

/** A new draft of the agent; with a club, ready to be sent. */
function revDraft(User $agent, ?Club $club, ?Client $client = null): Proposal
{
    $client ??= Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();

    if ($club !== null) {
        revSave($agent, $proposal, $club)->assertSessionHasNoErrors();
    }

    return $proposal->refresh();
}

/** Adds a variant to the group of the draft ("Aggiungi variante", DM-01). */
function revAddVariant(User $agent, Proposal $proposal): Proposal
{
    test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->post(route('proposals.variants.store', $proposal), ['step' => 5])
        ->assertSessionHasNoErrors();

    return Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->latest('id')->firstOrFail();
}

function revSubmit(User $agent, Proposal $proposal): TestResponse
{
    return test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->post(route('proposals.groups.submit', $proposal->proposal_group_id));
}

function revReject(User $actor, Proposal $proposal, ?string $reason = null): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.reject', $proposal->proposal_group_id), ['reason' => $reason]);
}

it('D-395: sends every variant of the group together, with history and audit', function () {
    $a = revDraft($this->agent, $this->lazio);
    $b = revAddVariant($this->agent, $a);

    revSubmit($this->agent, $a)
        ->assertRedirect(route('proposals.groups.show', $a->proposal_group_id))
        ->assertSessionHas('toast.message', 'Proposta n. '.$a->number.' inviata in approvazione');

    foreach ([$a, $b] as $variant) {
        $variant->refresh();
        expect($variant->status)->toBe(ProposalStatus::Submitted)
            ->and($variant->submitted_at?->toDateTimeString())->toBe(now()->toDateTimeString())
            ->and(ProposalEvent::query()->where('proposal_id', $variant->id)->where('type', ProposalEventType::Submitted->value)->count())->toBe(1);
    }

    $events = auditEventsOf(AuditAction::ProposalSubmitted);
    expect($events)->toHaveCount(2)
        ->and($events->first()?->old_values)->toBe(['status' => 'draft'])
        ->and($events->first()?->new_values)->toMatchArray(['status' => 'submitted', 'proposal_group_id' => $a->proposal_group_id, 'variant_letter' => 'A']);
});

it('D-395: refuses a group with a variant not ready and writes nothing', function () {
    $a = revDraft($this->agent, $this->lazio);
    $b = revAddVariant($this->agent, $a);
    revSave($this->agent, $b, null)->assertSessionHasNoErrors();

    revSubmit($this->agent, $a)
        ->assertSessionHas('toast.message', 'Prima dell\'invio correggi la proposta: varianti con club in errore o senza elementi: B');

    expect($a->refresh()->status)->toBe(ProposalStatus::Draft)
        ->and($b->refresh()->status)->toBe(ProposalStatus::Draft)
        ->and(ProposalEvent::query()->whereIn('type', [ProposalEventType::Submitted->value, ProposalEventType::Resubmitted->value])->count())->toBe(0)
        ->and(auditEventsOf(AuditAction::ProposalSubmitted))->toHaveCount(0);
});

it('D-395: a group already sent is not sent again', function () {
    $a = revDraft($this->agent, $this->lazio);
    revSubmit($this->agent, $a)->assertSessionHasNoErrors();

    revSubmit($this->agent, $a)
        ->assertSessionHas('toast.message', 'La proposta non si può inviare: è già in approvazione o è stata decisa');

    expect(auditEventsOf(AuditAction::ProposalSubmitted))->toHaveCount(1);
});

it('D-411: the group of an agent is not seen nor sent by another agent', function () {
    $a = revDraft($this->agent, $this->lazio);
    $other = User::factory()->agent()->create();

    $this->actingAs($other)->get(route('proposals.groups.show', $a->proposal_group_id))->assertForbidden();
    revSubmit($other, $a)->assertForbidden();

    expect($a->refresh()->status)->toBe(ProposalStatus::Draft);
});

it('D-411: a sent group is read only for the agent and in the configurator', function () {
    $a = revDraft($this->agent, $this->lazio);
    revSubmit($this->agent, $a);

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Proposals/Group')
            ->where('group.status', 'submitted')
            ->where('group.statusLabel', 'In approvazione')
            ->where('group.canSubmit', false)
            ->where('group.canReject', false)
            ->where('group.client', $a->client?->company_name)
            ->where('group.agent', 'Mario Verdi')
            ->where('group.variants.0.editUrl', null)
            ->where('group.variants.0.draft.listTotal', fn (string $total): bool => $total !== '0,00')
            ->where('group.history.0.label', 'Inviata in approvazione')
            ->where('group.backUrl', route('pages.proposals_mine')));

    revSave($this->agent, $a, $this->lazio, 90)->assertForbidden();
    $this->actingAs($this->agent)->get(route('proposals.edit', $a))->assertForbidden();
});

it('M07 F-01: the queue "Da approvare" lists the sent groups, the oldest first', function () {
    $first = revDraft($this->agent, $this->lazio);
    revSubmit($this->agent, $first);
    $this->travel(1)->hours();
    $second = revDraft($this->agent, $this->lazio);
    revAddVariant($this->agent, $second);
    revSubmit($this->agent, $second);
    revDraft($this->agent, $this->lazio);

    foreach ([$this->admin, $this->manager] as $reviewer) {
        $this->actingAs($reviewer)->get(route('pages.proposals_pending'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proposals/Pending')
                ->has('groups', 2)
                ->where('groups.0.number', $first->number)
                ->where('groups.0.agent', 'Mario Verdi')
                ->where('groups.0.variants', 1)
                ->where('groups.0.clubs', $this->lazio->short_name)
                ->where('groups.0.problems', [])
                ->where('groups.1.number', $second->number)
                ->where('groups.1.variants', 2)
                ->where('groups.1.totals.1.letter', 'B')
                ->where('groups.1.url', route('proposals.groups.show', $second->proposal_group_id)));
    }
});

it('D-411: admin and manager see the detail of a sent group and can reject it', function () {
    $a = revDraft($this->agent, $this->lazio);
    revSubmit($this->agent, $a);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.canSubmit', false)
            ->where('group.canReject', true)
            ->where('group.backUrl', route('pages.proposals_pending')));
});

it('D-412: the rejection applies to every variant of the group, with the reason', function () {
    $a = revDraft($this->agent, $this->lazio);
    $b = revAddVariant($this->agent, $a);
    revSubmit($this->agent, $a);

    revReject($this->admin, $a, 'Prezzo da rivedere')
        ->assertRedirect(route('pages.proposals_pending'))
        ->assertSessionHas('toast.message', 'Proposta n. '.$a->number.' rifiutata');

    foreach ([$a, $b] as $variant) {
        $variant->refresh();
        expect($variant->status)->toBe(ProposalStatus::Rejected)
            ->and($variant->decided_by)->toBe($this->admin->id)
            ->and($variant->last_rejection_reason)->toBe('Prezzo da rivedere');
        $event = ProposalEvent::query()->where('proposal_id', $variant->id)->where('type', ProposalEventType::Rejected->value)->sole();
        expect($event->notes)->toBe('Prezzo da rivedere')
            ->and($event->user_id)->toBe($this->admin->id);
    }

    $events = auditEventsOf(AuditAction::ProposalRejected);
    expect($events)->toHaveCount(2)
        ->and($events->first()?->reason)->toBe('Prezzo da rivedere')
        ->and($events->first()?->new_values)->toMatchArray(['status' => 'rejected', 'variant_letter' => 'A']);

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.status', 'rejected')
            ->where('group.decidedBy', 'Anna Neri')
            ->where('group.rejectionReason', 'Prezzo da rivedere')
            ->where('group.canSubmit', true)
            ->where('group.variants.0.editUrl', route('proposals.edit', ['proposal' => $a, 'passo' => 5])));
});

it('D-412: the reason is optional and a group no longer sent is not rejected again', function () {
    $a = revDraft($this->agent, $this->lazio);
    revSubmit($this->agent, $a);

    revReject($this->manager, $a)->assertSessionHasNoErrors();
    expect($a->refresh()->status)->toBe(ProposalStatus::Rejected)
        ->and($a->last_rejection_reason)->toBeNull();

    revReject($this->admin, $a, 'Ancora')
        ->assertSessionHas('toast.message', 'La proposta non è più in approvazione: ricarica la pagina');
    expect(auditEventsOf(AuditAction::ProposalRejected))->toHaveCount(1);
});

it('D-412: the agent does not reject and a draft is not rejected', function () {
    $a = revDraft($this->agent, $this->lazio);

    revReject($this->agent, $a)->assertForbidden();
    revReject($this->admin, $a)->assertSessionHas('toast.message', 'La proposta non è più in approvazione: ricarica la pagina');

    expect($a->refresh()->status)->toBe(ProposalStatus::Draft);
});

it('M07-CA6: the rejected group is corrected and sent again with the same number', function () {
    $a = revDraft($this->agent, $this->lazio);
    revSubmit($this->agent, $a);
    revReject($this->admin, $a, 'Più minuti');

    $this->actingAs($this->agent)->get(route('proposals.edit', ['proposal' => $a, 'passo' => 5]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.rejection.reason', 'Più minuti')
            ->where('draft.rejection.by', 'Anna Neri')
            ->where('draft.submitUrl', route('proposals.groups.submit', $a->proposal_group_id)));

    revSave($this->agent, $a, $this->lazio, 120)->assertSessionHasNoErrors();
    expect($a->refresh()->status)->toBe(ProposalStatus::Rejected);

    revSubmit($this->agent, $a)->assertSessionHasNoErrors();

    $a->refresh();
    expect($a->status)->toBe(ProposalStatus::Submitted)
        ->and(ProposalEvent::query()->where('proposal_id', $a->id)->where('type', ProposalEventType::Resubmitted->value)->count())->toBe(1)
        ->and(auditEventsOf(AuditAction::ProposalSubmitted)->last()?->old_values)->toBe(['status' => 'rejected']);
});

it('D-412: a rejected variant can be deleted, a sent one cannot (trigger)', function () {
    $a = revDraft($this->agent, $this->lazio);
    revSubmit($this->agent, $a);

    expect(fn () => DB::table('proposals')->where('id', $a->id)->delete())->toThrow(QueryException::class);

    revReject($this->admin, $a);
    $this->actingAs($this->agent)
        ->from(route('pages.proposals_mine'))
        ->delete(route('proposals.destroy', $a))
        ->assertSessionHasNoErrors();

    expect(Proposal::query()->whereKey($a->id)->exists())->toBeFalse()
        ->and(ProposalGroup::query()->whereKey($a->proposal_group_id)->exists())->toBeFalse()
        ->and(soleAuditEvent(AuditAction::ProposalDraftDeleted)->old_values)->toMatchArray(['status' => 'rejected']);
});
