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
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Lock of the work and withdrawal (M07 F-09, §9; D-100, D-216, D-444, D-446, D-449…D-453; slice F5,
| PR F5-01): admin or manager take the lock of a group waiting for approval when they open it; the
| others see it read only and their changes are refused; the lock lasts 30 minutes from the last
| action; the admin takes it over; the holder leaves it; the agent withdraws the group, not while it
| is worked. The routes are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->lazio = rlkClub($this);
    $this->agent = User::factory()->agent()->create(['first_name' => 'Mario', 'last_name' => 'Verdi']);
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create(['first_name' => 'Marta', 'last_name' => 'Gestori']);
});

function rlkClub(object $test): Club
{
    $club = Club::factory()->infront()->create(['name' => 'SS Lazio', 'code' => 'LAZIO']);
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
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => $tier->id, 'is_enabled' => true, 'quantity' => 4]);

    return $club;
}

/** A draft of the agent with Lazio TOP and 1′ of 2nd row, saved as the configurator does. */
function rlkDraft(User $agent, Club $club): Proposal
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

/** A group sent for approval; with two variants when asked. */
function rlkSubmitted(User $agent, Club $club, bool $twoVariants = false): Proposal
{
    $proposal = rlkDraft($agent, $club);

    if ($twoVariants) {
        test()->actingAs($agent)
            ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 5]))
            ->post(route('proposals.variants.store', $proposal), ['step' => 5])
            ->assertSessionHasNoErrors();
    }

    test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->post(route('proposals.groups.submit', $proposal->proposal_group_id))
        ->assertSessionHasNoErrors();

    return $proposal->refresh();
}

function rlkOpen(User $actor, Proposal $proposal): TestResponse
{
    return test()->actingAs($actor)->get(route('proposals.groups.show', $proposal->proposal_group_id));
}

function rlkPost(User $actor, Proposal $proposal, string $route, array $data = []): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route($route, $proposal->proposal_group_id), $data);
}

/** Holders of the lock on every variant of the group (the same on each, D-449). */
function rlkHolders(Proposal $proposal): array
{
    return Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->orderBy('id')->pluck('review_locked_by')->all();
}

it('D-446: opening the detail, admin or manager take the lock; the others read it only', function () {
    $a = rlkSubmitted($this->agent, $this->lazio, true);

    rlkOpen($this->manager, $a)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('group.reviewLock.holder', 'Marta Gestori')
        ->where('group.reviewLock.mine', true)
        ->where('group.canApprove', true)
        ->where('group.canRelease', true));

    expect(rlkHolders($a))->toEqual([$this->manager->id, $this->manager->id])
        ->and(auditEventsOf(AuditAction::ProposalReviewLockTaken))->toHaveCount(1);

    // A reload of the holder renews the lock without a new event (D-450, D-451).
    $this->travel(10)->minutes();
    rlkOpen($this->manager, $a)->assertOk();
    expect($a->refresh()->review_locked_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and(auditEventsOf(AuditAction::ProposalReviewLockTaken))->toHaveCount(1);

    // The admin sees it read only, with "Subentra"; the agent never takes the lock.
    rlkOpen($this->admin, $a)->assertInertia(fn (Assert $page) => $page
        ->where('group.reviewLock.holder', 'Marta Gestori')
        ->where('group.reviewLock.mine', false)
        ->where('group.canApprove', false)
        ->where('group.canReject', false)
        ->where('group.canTakeOver', true)
        ->where('group.canRelease', false)
        ->where('documentSlides.editable', false));
    rlkOpen($this->agent, $a)->assertInertia(fn (Assert $page) => $page
        ->where('group.canWithdraw', true)
        ->where('group.withdrawBlocked', true));

    expect(rlkHolders($a))->toEqual([$this->manager->id, $this->manager->id]);

    // The queue shows who works the group.
    $this->actingAs($this->admin)->get(route('pages.proposals_pending'))
        ->assertInertia(fn (Assert $page) => $page->where('groups.0.lockedBy', 'Marta Gestori'));
});

it('D-446: approval, rejection and the slides of the document are refused while another user holds the lock', function () {
    $a = rlkSubmitted($this->agent, $this->lazio);
    rlkOpen($this->manager, $a);

    rlkPost($this->admin, $a, 'proposals.groups.reject', ['reason' => 'No'])
        ->assertSessionHas('toast.message', 'La proposta è in lavorazione da Marta Gestori: ricarica la pagina');
    rlkPost($this->admin, $a, 'proposals.groups.approve', ['discounts' => []])
        ->assertSessionHas('toast.message', 'La proposta è in lavorazione da Marta Gestori: ricarica la pagina');
    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $a->proposal_group_id))
        ->put(route('proposals.groups.slides', $a->proposal_group_id), ['mode' => 'summary', 'excluded_slide_ids' => []])
        ->assertSessionHas('toast.message', 'La proposta è in lavorazione da Marta Gestori: ricarica la pagina');

    expect($a->refresh()->status)->toBe(ProposalStatus::Submitted);

    // The holder decides: the lock is freed with the decision.
    rlkPost($this->manager, $a, 'proposals.groups.reject', ['reason' => 'Prezzo'])->assertRedirect(route('pages.proposals_pending'));

    expect($a->refresh()->status)->toBe(ProposalStatus::Rejected)
        ->and(rlkHolders($a))->toEqual([null]);
});

it('D-450: after 30 minutes without actions the lock expires and another user takes it', function () {
    $a = rlkSubmitted($this->agent, $this->lazio);
    rlkOpen($this->manager, $a);

    $this->travel(31)->minutes();
    $this->flushSession();
    rlkOpen($this->admin, $a)->assertInertia(fn (Assert $page) => $page
        ->where('group.reviewLock.holder', 'Anna Neri')
        ->where('group.canApprove', true));

    rlkPost($this->admin, $a, 'proposals.groups.approve', ['discounts' => []])->assertSessionHasNoErrors();

    expect($a->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and(rlkHolders($a))->toEqual([null])
        ->and(auditEventsOf(AuditAction::ProposalReviewLockTaken))->toHaveCount(2);
});

it('D-446: the admin takes over the lock of another user, the manager cannot', function () {
    $a = rlkSubmitted($this->agent, $this->lazio);
    rlkOpen($this->admin, $a);

    rlkPost($this->manager, $a, 'proposals.groups.review.take-over')->assertForbidden();
    expect(rlkHolders($a))->toEqual([$this->admin->id]);

    rlkOpen($this->manager, $a);
    $this->travel(31)->minutes();
    $this->flushSession();
    rlkOpen($this->manager, $a);
    expect(rlkHolders($a))->toEqual([$this->manager->id]);

    rlkPost($this->admin, $a, 'proposals.groups.review.take-over')
        ->assertRedirect(route('proposals.groups.show', $a->proposal_group_id))
        ->assertSessionHas('toast.message', 'Hai preso in lavorazione la proposta n. '.$a->number);

    $event = soleAuditEvent(AuditAction::ProposalReviewLockTakenOver);
    expect(rlkHolders($a))->toEqual([$this->admin->id])
        ->and($event->old_values)->toBe(['review_locked_by' => 'Marta Gestori'])
        ->and($event->new_values['review_locked_by'])->toBe('Anna Neri');

    rlkPost($this->manager, $a, 'proposals.groups.reject')
        ->assertSessionHas('toast.message', 'La proposta è in lavorazione da Anna Neri: ricarica la pagina');
});

it('D-452: "Lascia la lavorazione" frees the lock of its holder only', function () {
    $a = rlkSubmitted($this->agent, $this->lazio);
    rlkOpen($this->manager, $a);

    rlkPost($this->admin, $a, 'proposals.groups.review.release')->assertRedirect(route('pages.proposals_pending'));
    expect(rlkHolders($a))->toEqual([$this->manager->id]);

    rlkPost($this->manager, $a, 'proposals.groups.review.release')
        ->assertRedirect(route('pages.proposals_pending'))
        ->assertSessionHas('toast.message', 'Hai lasciato la lavorazione della proposta n. '.$a->number);

    expect(rlkHolders($a))->toEqual([null])
        ->and(soleAuditEvent(AuditAction::ProposalReviewLockReleased)->user_id)->toBe($this->manager->id);
});

it('D-100, D-444: the agent withdraws the group: every variant goes back to draft and can be sent again', function () {
    $a = rlkSubmitted($this->agent, $this->lazio, true);

    rlkPost($this->agent, $a, 'proposals.groups.withdraw')
        ->assertRedirect(route('proposals.groups.show', $a->proposal_group_id))
        ->assertSessionHas('toast.message', 'Proposta n. '.$a->number.' ritirata: è di nuovo in bozza');

    $variants = Proposal::query()->where('proposal_group_id', $a->proposal_group_id)->get();
    expect($variants->pluck('status')->unique()->all())->toBe([ProposalStatus::Draft])
        ->and($variants->pluck('submitted_at')->filter()->all())->toBe([])
        ->and(ProposalEvent::query()->where('type', ProposalEventType::Withdrawn->value)->count())->toBe(2)
        ->and(auditEventsOf(AuditAction::ProposalWithdrawn))->toHaveCount(2);

    rlkOpen($this->agent, $a)->assertInertia(fn (Assert $page) => $page
        ->where('group.status', 'draft')
        ->where('group.canSubmit', true)
        ->where('group.canWithdraw', false)
        ->where('group.variants.0.editUrl', route('proposals.edit', ['proposal' => $a->id, 'passo' => 5])));

    // Sent again: a new "submitted" in the history, the group back in the queue.
    rlkPost($this->agent, $a, 'proposals.groups.submit')->assertSessionHasNoErrors();
    expect($a->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and(ProposalEvent::query()->where('proposal_id', $a->id)->where('type', ProposalEventType::Submitted->value)->count())->toBe(2);
});

it('M07 §9: the agent cannot withdraw a group worked by admin or manager, until the lock expires', function () {
    $a = rlkSubmitted($this->agent, $this->lazio);
    rlkOpen($this->manager, $a);

    rlkPost($this->agent, $a, 'proposals.groups.withdraw')
        ->assertSessionHas('toast.message', 'La proposta è in lavorazione da Marta Gestori e non si può ritirare ora: riprova più tardi');
    expect($a->refresh()->status)->toBe(ProposalStatus::Submitted);

    $this->travel(31)->minutes();
    $this->flushSession();
    rlkPost($this->agent, $a, 'proposals.groups.withdraw')->assertSessionHasNoErrors();

    expect($a->refresh()->status)->toBe(ProposalStatus::Draft)
        ->and($a->review_locked_by)->toBeNull();
});

it('D-100: only the agent of the group withdraws it, only while it waits', function () {
    $a = rlkSubmitted($this->agent, $this->lazio);
    $other = User::factory()->agent()->create();

    rlkPost($other, $a, 'proposals.groups.withdraw')->assertForbidden();
    rlkPost($this->manager, $a, 'proposals.groups.withdraw')->assertForbidden();

    rlkPost($this->agent, $a, 'proposals.groups.withdraw');
    rlkPost($this->agent, $a, 'proposals.groups.withdraw')
        ->assertSessionHas('toast.message', 'La proposta non è più in approvazione: ricarica la pagina');
});
