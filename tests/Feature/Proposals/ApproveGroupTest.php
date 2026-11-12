<?php

use App\Domain\Inventory\Services\InventoryAvailability;
use App\Enums\AuditAction;
use App\Enums\CommitmentReleaseReason;
use App\Enums\DiscountType;
use App\Enums\InventoryResource;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
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
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Approval of a group with the discounts, and choice of the client (M07 §4, FL-01, F-04, F-05, §8,
| §9, CA1…CA3; D-097, D-103, D-192, D-216, D-396, D-401, D-408…D-410; slice DM, PR DM-03b): every
| variant is checked again on the current list and residuals, all of them commit the inventory, the
| working version is frozen as version 1 with the current prices and the discount; the client then
| chooses one variant of an approved group and the others are released. The routes are in the route
| × role matrix (RouteMatrixTest); the concurrency is in tests/Concurrency/ApproveGroupTest.php.
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

    $this->lazio = apprClub($this, 'SS Lazio', 'LAZIO');
    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create();
});

/** A club with the tier TOP at 155,680.00 € (M07-CA2) and the 2nd row at 1,000.00 € per minute. */
function apprClub(object $test, string $name, string $code, int $contracts = 2): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, 'code' => $code]);
    ClubEnrollment::query()->create(['competition_season_id' => $test->league->id, 'club_id' => $club->id]);

    LedPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'led_format_id' => $test->format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'inventory_pool_id' => $test->pool->id, 'is_enabled' => true, 'seconds_per_match' => 600]);
    PartnershipPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'partnership_tier_id' => $test->tier->id, 'season_price' => '155680.00']);
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => $test->tier->id, 'is_enabled' => true, 'quantity' => $contracts]);

    return $club;
}

/**
 * Saves one club of a draft as the configurator does.
 *
 * @param  array{tier?: bool, seconds?: int}  $choice
 */
function apprSave(User $agent, Proposal $proposal, Club $club, array $choice): void
{
    $proposal->refresh();
    $version = $proposal->workingVersion;
    $seconds = $choice['seconds'] ?? 0;

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
                'partnership_tier_id' => ($choice['tier'] ?? false) ? PartnershipTier::query()->where('code', 'TOP')->value('id') : null,
                'led_format_id' => $seconds > 0 ? LedFormat::query()->where('code', 'FILA_2')->value('id') : null,
                'led_seconds' => $seconds,
                'seats' => [],
            ]],
        ])
        ->assertSessionHasNoErrors();
}

/**
 * A group sent for approval: one variant per choice (A, B…).
 *
 * @param  list<array{tier?: bool, seconds?: int}>  $choices
 */
function apprGroup(User $agent, Club $club, array $choices): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $first = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    apprSave($agent, $first, $club, $choices[0]);

    foreach (array_slice($choices, 1) as $choice) {
        test()->actingAs($agent)
            ->from(route('proposals.edit', ['proposal' => $first, 'passo' => 5]))
            ->post(route('proposals.variants.store', $first), ['step' => 5])
            ->assertSessionHasNoErrors();
        $variant = Proposal::query()->where('proposal_group_id', $first->proposal_group_id)->latest('id')->firstOrFail();
        apprSave($agent, $variant, $club, $choice);
    }

    test()->actingAs($agent)->post(route('proposals.groups.submit', $first->proposal_group_id))->assertSessionHasNoErrors();

    return $first->refresh();
}

/**
 * @param  array<int, array{type: string, value: string}>  $discounts
 */
function apprApprove(User $actor, Proposal $proposal, array $discounts = []): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id), ['discounts' => $discounts]);
}

function apprVariant(Proposal $proposal, string $letter): Proposal
{
    return Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->where('variant_letter', $letter)->firstOrFail();
}

function apprRemaining(object $test, InventoryResource $resource, int $valueId): int
{
    foreach (app(InventoryAvailability::class)->forClub($test->season, $test->lazio)[$resource->value] as $availability) {
        if ($availability->valueId === $valueId) {
            return $availability->remaining();
        }
    }

    return -1;
}

it('M07-CA2: 15% on 155,680 € gives 132,328 €; the club rows stay at list price; version 1 frozen', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true]]);

    apprApprove($this->manager, $a, [$a->id => ['type' => 'percent', 'value' => '15']])
        ->assertRedirect(route('proposals.groups.show', $a->proposal_group_id))
        ->assertSessionHas('toast.message', 'Proposta n. '.$a->number.' approvata');

    $a->refresh();
    $version = ProposalVersion::query()->findOrFail($a->current_version_id);

    expect($a->status)->toBe(ProposalStatus::Approved)
        ->and($a->decided_by)->toBe($this->manager->id)
        ->and($a->workingVersion()->exists())->toBeFalse()
        ->and($version->number)->toBe(1)
        ->and($version->frozen_by)->toBe($this->manager->id)
        ->and($version->list_total)->toBe('155680.00')
        ->and($version->discount_type)->toBe(DiscountType::Percent)
        ->and($version->discount_value)->toBe('15.00')
        ->and($version->discount_amount)->toBe('23352.00')
        ->and($version->final_total)->toBe('132328.00')
        ->and($version->client_name)->toBe($a->client?->company_name)
        ->and($version->clubs()->sole()->club_total)->toBe('155680.00');

    $events = ProposalEvent::query()->where('proposal_id', $a->id)->orderBy('id')->get();
    expect($events->pluck('type')->all())->toContain(ProposalEventType::DiscountSet, ProposalEventType::Approved)
        ->and($events->firstWhere('type', ProposalEventType::DiscountSet)?->notes)->toBe('Sconto 15,00% (€ 23.352,00): totale finale € 132.328,00');

    expect(soleAuditEvent(AuditAction::ProposalApproved)->new_values)->toMatchArray([
        'status' => 'approved', 'version' => 1, 'list_total' => '155680.00', 'discount_type' => 'percent', 'discount_amount' => '23352.00', 'final_total' => '132328.00',
    ]);
});

it('M07-CA3: a discount of 10,000 € shows the equivalent percentage, before and after the approval', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true]]);

    $this->actingAs($this->admin)
        ->get(route('proposals.groups.show', ['group' => $a->proposal_group_id, 'sconti' => [$a->id => ['type' => 'amount', 'value' => '10.000']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.variants.0.discount.percent', '6,42')
            ->where('group.variants.0.discount.finalTotal', '145.680,00')
            ->where('group.canApprove', true));

    apprApprove($this->admin, $a, [$a->id => ['type' => 'amount', 'value' => '10.000']])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.status', 'approved')
            ->where('group.decidedBy', 'Anna Neri')
            ->where('group.variants.0.discount.label', 'Sconto in importo (pari al 6,42%)')
            ->where('group.variants.0.discount.finalTotal', '145.680,00')
            ->where('group.variants.0.draft.listTotal', '155.680,00')
            ->where('group.canApprove', false));
});

it('M07-CA1: a group waiting for approval commits nothing; the approval lowers the residuals', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true, 'seconds' => 60]]);

    expect(InventoryCommitment::query()->count())->toBe(0)
        ->and(apprRemaining($this, InventoryResource::Partnership, $this->tier->id))->toBe(2)
        ->and(apprRemaining($this, InventoryResource::Pool, $this->pool->id))->toBe(600);

    apprApprove($this->admin, $a)->assertSessionHasNoErrors();

    $version = ProposalVersion::query()->findOrFail($a->refresh()->current_version_id);
    expect(apprRemaining($this, InventoryResource::Partnership, $this->tier->id))->toBe(1)
        ->and(apprRemaining($this, InventoryResource::Pool, $this->pool->id))->toBe(540)
        ->and(InventoryCommitment::query()->where('proposal_id', $a->id)->where('proposal_version_id', $version->id)->count())->toBe(2)
        ->and(InventoryCommitment::query()->where('resource_type', 'pool')->sole()->starts_on->toDateString())->toBe($version->starts_on?->toDateString())
        ->and($version->final_total)->toBe($version->list_total)
        ->and($version->discount_type)->toBeNull();
});

it('D-396: all the variants commit together, so their sum must fit the residual', function () {
    PartnershipCapacity::query()->where('club_id', $this->lazio->id)->update(['quantity' => 1]);
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true], ['tier' => true]]);

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.availability.0.requested', '2 contratti')
            ->where('group.availability.0.remaining', '1 contratto')
            ->where('group.availability.0.letters', 'A, B')
            ->where('group.availability.0.covered', false)
            ->has('group.problems', 1));

    apprApprove($this->admin, $a)
        ->assertSessionHas('toast.message', 'Approvazione bloccata: SS Lazio, Top: richiesti 2 contratti dalle varianti A, B, disponibili 1 contratto');

    expect($a->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and(apprVariant($a, 'B')->status)->toBe(ProposalStatus::Submitted)
        ->and(InventoryCommitment::query()->count())->toBe(0)
        ->and(ProposalVersion::query()->whereNotNull('frozen_at')->count())->toBe(0)
        ->and(auditEventsOf(AuditAction::ProposalApproved))->toHaveCount(0);
});

it('M07 §9: of two groups waiting on the last contract the first approved wins, the other is blocked', function () {
    PartnershipCapacity::query()->where('club_id', $this->lazio->id)->update(['quantity' => 1]);
    $first = apprGroup($this->agent, $this->lazio, [['tier' => true]]);
    $second = apprGroup($this->agent, $this->lazio, [['tier' => true]]);

    apprApprove($this->admin, $first)->assertSessionHasNoErrors();
    // The error "esaurito" of the variant is said once, with the numbers, by the shortfall.
    apprApprove($this->admin, $second)->assertSessionHas('toast.message', 'Approvazione bloccata: SS Lazio, Top: richiesti 1 contratto, disponibili 0 contratti');

    expect($first->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and($second->refresh()->status)->toBe(ProposalStatus::Submitted);
});

it('D-409: the approval freezes the current prices and the detail shows what changed since the sending', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true]]);
    PartnershipPrice::query()->where('club_id', $this->lazio->id)->update(['season_price' => '160000.00']);

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.variants.0.repricing.total', fn (string $text): bool => str_contains($text, '155.680,00') && str_contains($text, '160.000,00'))
            ->where('group.variants.0.discount.listTotal', '160.000,00'));

    apprApprove($this->admin, $a)->assertSessionHasNoErrors();

    $version = ProposalVersion::query()->findOrFail($a->refresh()->current_version_id);
    expect($version->list_total)->toBe('160000.00')
        ->and($version->clubs()->sole()->partnership_price)->toBe('160000.00');
});

it('M07 §8: a discount out of range or not a number is refused and nothing is written', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true]]);

    apprApprove($this->admin, $a, [$a->id => ['type' => 'percent', 'value' => '120']])
        ->assertSessionHas('toast.message', 'Lo sconto va da 0 a 100% o da 0 al totale di listino');
    apprApprove($this->admin, $a, [$a->id => ['type' => 'amount', 'value' => '200.000']])
        ->assertSessionHas('toast.message', 'Lo sconto va da 0 a 100% o da 0 al totale di listino');
    apprApprove($this->admin, $a, [$a->id => ['type' => 'percent', 'value' => 'quindici']])
        ->assertSessionHasErrors("discounts.{$a->id}.value");

    expect($a->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and(InventoryCommitment::query()->count())->toBe(0);
});

it('D-216: a group already approved is not approved again', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true]]);
    apprApprove($this->admin, $a)->assertSessionHasNoErrors();

    apprApprove($this->manager, $a)->assertSessionHas('toast.message', 'La proposta non è più in approvazione: ricarica la pagina');

    expect(InventoryCommitment::query()->count())->toBe(1)
        ->and(auditEventsOf(AuditAction::ProposalApproved))->toHaveCount(1);
});

it('D-011: the agent does not approve and never sees the discount', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true]]);

    apprApprove($this->agent, $a)->assertForbidden();
    apprApprove($this->admin, $a, [$a->id => ['type' => 'percent', 'value' => '15']]);

    $this->actingAs($this->agent)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.status', 'approved')
            ->where('group.variants.0.discount', null)
            ->where('group.variants.0.draft.listTotal', '155.680,00')
            ->where('group.availability', null)
            ->where('group.canChoose', false));

    $this->actingAs($this->agent)->get(route('pages.proposals_mine'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('drafts.0.statusLabel', 'Approvata')
            ->where('drafts.0.listTotal', '155.680,00')
            ->where('drafts.0.editable', false));
});

it('D-198: the frozen version and its commitments are never changed nor deleted', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true]]);
    apprApprove($this->admin, $a);
    $versionId = $a->refresh()->current_version_id;

    expect(fn () => DB::table('proposal_versions')->where('id', $versionId)->update(['final_total' => '1.00']))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposals')->where('id', $a->id)->delete())->toThrow(QueryException::class);
});

it('D-410: the choice of the client keeps one variant and releases the others', function () {
    $a = apprGroup($this->agent, $this->lazio, [['tier' => true], ['tier' => true, 'seconds' => 60]]);
    apprApprove($this->admin, $a)->assertSessionHasNoErrors();
    $b = apprVariant($a, 'B');
    expect(apprRemaining($this, InventoryResource::Partnership, $this->tier->id))->toBe(0);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.canChoose', true));

    $this->actingAs($this->manager)
        ->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $b->id])
        ->assertSessionHas('toast.message', 'Scelta del cliente registrata: variante B (proposta n. '.$b->number.')');

    expect($a->refresh()->status)->toBe(ProposalStatus::NotChosen)
        ->and($b->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and(apprRemaining($this, InventoryResource::Partnership, $this->tier->id))->toBe(1)
        ->and(InventoryCommitment::query()->where('proposal_id', $a->id)->whereNull('released_at')->count())->toBe(0)
        ->and(InventoryCommitment::query()->where('proposal_id', $a->id)->sole()->release_reason)->toBe(CommitmentReleaseReason::NotChosen)
        ->and(InventoryCommitment::query()->where('proposal_id', $b->id)->whereNull('released_at')->count())->toBe(2)
        ->and(ProposalEvent::query()->where('type', ProposalEventType::ClientChosen->value)->count())->toBe(2)
        ->and(auditEventsOf(AuditAction::ProposalClientChosen))->toHaveCount(2);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $a->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.status', 'approved')
            ->where('group.chosenLetter', 'B')
            ->where('group.variants.0.statusLabel', 'Non scelta')
            ->where('group.canChoose', false));
});

it('D-410: the choice is recorded once, only on an approved group of more variants', function () {
    $single = apprGroup($this->agent, $this->lazio, [['tier' => true]]);
    apprApprove($this->admin, $single);

    $this->actingAs($this->admin)
        ->post(route('proposals.groups.choose', $single->proposal_group_id), ['proposal_id' => $single->id])
        ->assertSessionHas('toast.message', 'La scelta del cliente si registra solo su una proposta approvata con almeno due varianti, una volta sola');

    $a = apprGroup($this->agent, $this->lazio, [['seconds' => 60], ['seconds' => 30]]);
    $this->actingAs($this->admin)
        ->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $a->id])
        ->assertSessionHas('toast.message', 'La scelta del cliente si registra solo su una proposta approvata con almeno due varianti, una volta sola');

    apprApprove($this->admin, $a);
    $this->actingAs($this->admin)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $single->id])
        ->assertSessionHas('toast.message', 'La variante scelta non appartiene a questa proposta');
    $this->actingAs($this->admin)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $a->id])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $a->id])
        ->assertSessionHas('toast.message', 'La scelta del cliente si registra solo su una proposta approvata con almeno due varianti, una volta sola');

    $this->actingAs($this->agent)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $a->id])->assertForbidden();
});

it('M07 F-01: "Tutte" lists every group with its status, the filter by status and the way back', function () {
    $approved = apprGroup($this->agent, $this->lazio, [['tier' => true], ['seconds' => 60]]);
    apprApprove($this->admin, $approved, [$approved->id => ['type' => 'percent', 'value' => '15']]);
    $this->travel(1)->minutes();
    $waiting = apprGroup($this->agent, $this->lazio, [['seconds' => 30]]);

    foreach ([$this->admin, $this->manager] as $reviewer) {
        $this->actingAs($reviewer)->get(route('pages.proposals_all'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proposals/All')
                ->has('groups', 2)
                ->where('groups.0.number', $waiting->number)
                ->where('groups.0.statusLabel', 'In approvazione')
                ->where('groups.1.number', $approved->number)
                ->where('groups.1.statusLabel', 'Approvata')
                ->where('groups.1.clubs', $this->lazio->short_name)
                ->where('groups.1.totals.0.total', '155.680,00')
                ->where('groups.1.totals.0.final', '132.328,00')
                ->where('groups.1.totals.1.final', null));
    }

    $this->actingAs($this->admin)->get(route('pages.proposals_all', ['stato' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'approved')
            ->has('groups', 1)
            ->where('groups.0.number', $approved->number));

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $approved->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.backUrl', route('pages.proposals_all'))
            ->where('group.backLabel', 'Torna a tutte le proposte'));
    $this->actingAs($this->admin)->get(route('proposals.groups.show', $waiting->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.backUrl', route('pages.proposals_pending')));
    $this->actingAs($this->agent)->get(route('pages.proposals_all'))->assertForbidden();
});
