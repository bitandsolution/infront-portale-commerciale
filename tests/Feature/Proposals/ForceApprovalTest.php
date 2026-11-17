<?php

use App\Domain\Inventory\Services\InventoryAvailability;
use App\Enums\AuditAction;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Forcing of an approval beyond the availability (M07 F-05, RB-06, CA4; M04 F-10, §9, §10, CA8; D-064,
| D-070, D-459…D-462; slice F5, PR F5-03): only the admin, with a reason of at least
| forced_reason_min_length characters, and only when the problems are quantities beyond the residual;
| forced commitments and version, history "forced", audit proposal.forced, residual negative in the
| availability. The route is in the route × role matrix (RouteMatrixTest); the concurrency in
| tests/Concurrency/ApproveGroupTest.php.
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

    // The last contract of the tier Top and 10′ of 2nd row per match.
    $this->lazio = Club::factory()->infront()->create(['name' => 'SS Lazio', 'code' => 'LAZIO']);
    ClubEnrollment::query()->create(['competition_season_id' => $this->league->id, 'club_id' => $this->lazio->id]);
    LedPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'led_format_id' => $this->format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'inventory_pool_id' => $this->pool->id, 'is_enabled' => true, 'seconds_per_match' => 600]);
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => $this->tier->id, 'season_price' => '155680.00']);
    $this->contracts = PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'partnership_tier_id' => $this->tier->id, 'is_enabled' => true, 'quantity' => 1]);

    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create();
});

/** A group of one proposal with the tier Top and 1′ of 2nd row, sent for approval. */
function frcGroup(object $test): Proposal
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
            'clubs' => [[
                'club_id' => $test->lazio->id,
                'partnership_tier_id' => $test->tier->id,
                'led_format_id' => $test->format->id,
                'led_seconds' => 60,
                'seats' => [],
            ]],
        ])
        ->assertSessionHasNoErrors();
    test()->actingAs($test->agent)->post(route('proposals.groups.submit', $proposal->proposal_group_id))->assertSessionHasNoErrors();

    return $proposal->refresh();
}

/** Two groups on the last contract: the first one approved, the second one blocked by the residual. */
function frcBlocked(object $test): Proposal
{
    $first = frcGroup($test);
    $second = frcGroup($test);

    test()->actingAs($test->admin)
        ->from(route('proposals.groups.show', $first->proposal_group_id))
        ->post(route('proposals.groups.approve', $first->proposal_group_id))
        ->assertSessionHasNoErrors();

    return $second;
}

function frcForce(User $actor, Proposal $proposal, string $reason): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.force', $proposal->proposal_group_id), ['forced_reason' => $reason]);
}

function frcTierRemaining(object $test): int
{
    foreach (app(InventoryAvailability::class)->forClub($test->season, $test->lazio)['partnership'] as $availability) {
        if ($availability->valueId === $test->tier->id) {
            return $availability->remaining();
        }
    }

    return 0;
}

it('M07-CA4, M04-CA8: the admin forces the approval beyond the residual, with the reason in history and audit', function () {
    $proposal = frcBlocked($this);

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.forceable', true)
            ->where('group.canForce', true)
            ->where('group.forcedReasonMin', 10)
            ->where('group.forceNeeds.0.club', 'SS Lazio')
            ->where('group.forceNeeds.0.requested', '1 contratto')
            ->where('group.forceNeeds.0.remaining', '0 contratti')
            ->where('group.forceNeeds.0.after', '−1 contratto'));

    frcForce($this->admin, $proposal, '  Accordo del direttore commerciale con il club  ')
        ->assertRedirect(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertSessionHas('toast.message', 'Proposta n. '.$proposal->number.' approvata con forzatura');

    $reason = 'Accordo del direttore commerciale con il club';
    $commitments = InventoryCommitment::query()->where('proposal_id', $proposal->id)->get();
    $tier = $commitments->firstWhere('resource_type', InventoryResource::Partnership);
    $led = $commitments->firstWhere('resource_type', InventoryResource::Pool);
    /** @var ProposalVersion $version */
    $version = $proposal->refresh()->currentVersion;

    expect($proposal->status)->toBe(ProposalStatus::Approved)
        ->and($tier?->is_forced)->toBeTrue()
        ->and($tier?->forced_reason)->toBe($reason)
        ->and($led?->is_forced)->toBeFalse()
        ->and($led?->forced_reason)->toBeNull()
        ->and($version->is_forced)->toBeTrue()
        ->and($version->forced_reason)->toBe($reason)
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->orderBy('id')->pluck('type')->map->value->slice(-2)->values()->all())
        ->toBe([ProposalEventType::Forced->value, ProposalEventType::Approved->value])
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::Forced->value)->value('notes'))->toBe($reason)
        ->and(frcTierRemaining($this))->toBe(-1);

    $audit = auditEventsOf(AuditAction::ProposalForced);
    expect($audit)->toHaveCount(1)
        ->and($audit[0]->new_values['forced_reason'] ?? null)->toBe($reason)
        ->and($audit[0]->new_values['resources'] ?? null)->toBe('SS Lazio, Top: richiesti 1 contratto, disponibili 0 contratti');

    // M04 §10, D-462: the residual is negative and said "oltre la capacità"; the detail marks the variant.
    $availability = collect(app(InventoryAvailability::class)->forClub($this->season, $this->lazio)['partnership'])->firstWhere('valueId', $this->tier->id);
    expect($availability?->isOverCapacity())->toBeTrue();

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.variants.0.forcedReason', $reason));
});

it('D-070, M07 RB-06: the manager sees the conflict without "Forza" and the route refuses him', function () {
    $proposal = frcBlocked($this);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.forceable', true)
            ->where('group.canForce', false));

    frcForce($this->manager, $proposal, 'Accordo del direttore commerciale')->assertForbidden();

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and(auditEventsOf(AuditAction::ProposalForced))->toHaveCount(0);
});

it('M04 §9: without a reason of at least 10 characters nothing is written', function () {
    $proposal = frcBlocked($this);

    frcForce($this->admin, $proposal, '  breve   ')
        ->assertSessionHasErrors(['forced_reason' => 'Scrivi la motivazione della forzatura (almeno 10 caratteri)']);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and(InventoryCommitment::query()->where('proposal_id', $proposal->id)->count())->toBe(0);
});

it('D-459: a problem other than a quantity beyond the residual is never forced', function () {
    $proposal = frcBlocked($this);
    // The 2nd row is no longer on sale for the club: not a quantity, so not forceable.
    LedPrice::query()->where('club_id', $this->lazio->id)->delete();

    $this->actingAs($this->admin)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.forceable', false)
            ->where('group.canForce', false));

    frcForce($this->admin, $proposal, 'Accordo del direttore commerciale')
        ->assertSessionHas('toast.message', fn (string $message): bool => str_starts_with($message, 'La forzatura copre solo le quantità oltre il residuo'));

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and(InventoryCommitment::query()->where('proposal_id', $proposal->id)->count())->toBe(0);
});

it('D-459: a resource not managed for the club is never forced', function () {
    $proposal = frcBlocked($this);
    $this->contracts->update(['is_enabled' => false]);

    frcForce($this->admin, $proposal, 'Accordo del direttore commerciale')
        ->assertSessionHas('toast.message', fn (string $message): bool => str_starts_with($message, 'La forzatura copre solo le quantità oltre il residuo'));

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Submitted);
});

it('D-459: when the residual is enough again the group is approved without forcing', function () {
    $proposal = frcBlocked($this);
    $this->contracts->update(['quantity' => 2]);

    frcForce($this->admin, $proposal, 'Accordo del direttore commerciale')
        ->assertSessionHas('toast.message', 'Proposta n. '.$proposal->number.' approvata');

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and(InventoryCommitment::query()->where('proposal_id', $proposal->id)->where('is_forced', true)->count())->toBe(0)
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::Forced->value)->count())->toBe(0)
        ->and(auditEventsOf(AuditAction::ProposalForced))->toHaveCount(0);
});
