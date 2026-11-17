<?php

use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\AuditEvent;
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
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Change of a variant waiting for approval (M07 §4, F-03; D-098, D-445, D-454…D-458; slice F5, PR
| F5-02): who holds the lock of the work opens the configurator in review mode from the detail of the
| group, steps 2 to 5, client and season fixed; saves and "Copia su altri club" use the same checks
| of the agent's configurator; one "Modificata in approvazione" per variant and work in the history,
| the audit at every save. The routes are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->lazio = rveClub($this, 'SS Lazio', 'LAZIO');
    $this->roma = rveClub($this, 'AS Roma', 'ROMA');
    $this->agent = User::factory()->agent()->create(['first_name' => 'Mario', 'last_name' => 'Verdi']);
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create(['first_name' => 'Marta', 'last_name' => 'Gestori']);
});

function rveClub(object $test, string $name, string $code): Club
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
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => $tier->id, 'is_enabled' => true, 'quantity' => 4]);

    return $club;
}

/**
 * A club as the configurator sends it: TOP, and the 2nd row with the seconds given (null = no LED).
 *
 * @return array<string, mixed>
 */
function rveClubData(Club $club, ?int $seconds): array
{
    return [
        'club_id' => $club->id,
        'partnership_tier_id' => PartnershipTier::query()->where('code', 'TOP')->value('id'),
        'led_format_id' => $seconds !== null ? LedFormat::query()->where('code', 'FILA_2')->value('id') : null,
        'led_seconds' => $seconds ?? 0,
        'seats' => [],
    ];
}

/**
 * The body of a save of the proposal with the clubs given; header fields can be replaced.
 *
 * @param  list<array<string, mixed>>  $clubs
 * @param  array<string, mixed>  $header
 * @return array<string, mixed>
 */
function rveBody(Proposal $proposal, array $clubs, array $header = []): array
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    return [
        'lock_version' => $proposal->lock_version,
        'client_id' => $proposal->client_id,
        'season_id' => $proposal->season_id,
        'starts_on' => $version?->starts_on?->toDateString(),
        'ends_on' => $version?->ends_on?->toDateString(),
        'clubs' => $clubs,
        ...$header,
    ];
}

/**
 * A group of the agent sent for approval: Lazio with 1′ of 2nd row, and Roma with TOP alone when
 * asked.
 */
function rveSubmitted(User $agent, bool $withRoma = false): Proposal
{
    $client = Client::factory()->ownedBy($agent)->create();
    test()->actingAs($agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    $clubs = [rveClubData(test()->lazio, 60)];

    if ($withRoma) {
        $clubs[] = rveClubData(test()->roma, null);
    }

    test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.update', $proposal), rveBody($proposal, $clubs))
        ->assertSessionHasNoErrors();
    test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->post(route('proposals.groups.submit', $proposal->proposal_group_id))
        ->assertSessionHasNoErrors();

    return $proposal->refresh();
}

function rveSave(User $actor, Proposal $proposal, array $body): TestResponse
{
    return test()->actingAs($actor)
        ->from(route('proposals.review.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->put(route('proposals.review.update', $proposal), $body);
}

function rveRow(Proposal $proposal, Club $club): ?ProposalVersionClub
{
    return ProposalVersionClub::query()
        ->where('proposal_version_id', $proposal->refresh()->workingVersion?->id)
        ->where('club_id', $club->id)
        ->first();
}

it('D-445, D-454: who holds the lock opens the variant in review mode, at the steps 2 to 5', function () {
    $proposal = rveSubmitted($this->agent);

    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.variants.0.reviewUrl', route('proposals.review.edit', ['proposal' => $proposal->id, 'passo' => 4])));

    $this->actingAs($this->manager)->get(route('proposals.review.edit', ['proposal' => $proposal, 'passo' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Proposals/Configurator')
            ->where('step', 2)
            ->where('lastStep', 5)
            ->where('review.agent', 'Mario Verdi')
            ->where('review.groupUrl', route('proposals.groups.show', $proposal->proposal_group_id))
            ->where('draft.saveUrl', route('proposals.review.update', $proposal))
            ->where('draft.copyUrl', route('proposals.review.copy-clubs', $proposal))
            ->where('draft.deleteUrl', null)
            ->where('draft.submitUrl', null)
            ->where('draft.canAddVariant', false)
            ->where('documentSlides', null));

    $this->actingAs($this->manager)->get(route('proposals.review.edit', ['proposal' => $proposal, 'passo' => 6]))
        ->assertInertia(fn (Assert $page) => $page->where('step', 5));

    // The agent's view of the group has no "Modifica".
    $this->actingAs($this->agent)->get(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.variants.0.reviewUrl', null));
});

it('D-098, D-456: the saves change the waiting variant; one history event per work, the audit at every save', function () {
    $proposal = rveSubmitted($this->agent);
    $before = $proposal->lock_version;
    $this->actingAs($this->manager)->get(route('proposals.review.edit', ['proposal' => $proposal, 'passo' => 4]));

    rveSave($this->manager, $proposal, rveBody($proposal, [rveClubData($this->lazio, 90)]))->assertSessionHasNoErrors();
    rveSave($this->manager, $proposal, rveBody($proposal, [rveClubData($this->lazio, 120)]))->assertSessionHasNoErrors();

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and($proposal->lock_version)->toBe($before + 2)
        ->and(rveRow($proposal, $this->lazio)?->led_seconds_per_match)->toBe(120)
        ->and(AuditEvent::query()->where('action', AuditAction::ProposalDraftUpdated->value)->where('user_id', $this->manager->id)->count())->toBe(2)
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::EditedInReview->value)->pluck('user_id')->all())->toBe([$this->manager->id]);

    // Another work (the admin takes over): a second event.
    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.review.take-over', $proposal->proposal_group_id));
    rveSave($this->admin, $proposal, rveBody($proposal, [rveClubData($this->lazio, 60)]))->assertSessionHasNoErrors();

    expect(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::EditedInReview->value)->pluck('user_id')->all())
        ->toBe([$this->manager->id, $this->admin->id]);
});

it('M06 §8: in review mode a choice beyond the residual is refused like in the configurator of the agent', function () {
    $proposal = rveSubmitted($this->agent);
    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id));

    rveSave($this->manager, $proposal, rveBody($proposal, [rveClubData($this->lazio, 660)]))
        ->assertSessionHasErrors('clubs.'.$this->lazio->id.'.led_seconds');

    expect(rveRow($proposal, $this->lazio)?->led_seconds_per_match)->toBe(60);
});

it('D-446: while another user holds the lock the page leads back to the group and the saves are refused', function () {
    $proposal = rveSubmitted($this->agent);
    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id));

    $this->actingAs($this->admin)->get(route('proposals.review.edit', $proposal))
        ->assertRedirect(route('proposals.groups.show', $proposal->proposal_group_id))
        ->assertSessionHas('toast.message', 'La proposta è in lavorazione da Marta Gestori: ricarica la pagina');
    rveSave($this->admin, $proposal, rveBody($proposal, [rveClubData($this->lazio, 90)]))
        ->assertSessionHas('toast.message', 'La proposta è in lavorazione da Marta Gestori: ricarica la pagina');

    expect(rveRow($proposal, $this->lazio)?->led_seconds_per_match)->toBe(60);

    // After 30 minutes without actions the lock expires: the admin opens the variant and takes it.
    $this->travel(31)->minutes();
    $this->actingAs($this->admin)->get(route('proposals.review.edit', $proposal))->assertOk();

    expect($proposal->refresh()->review_locked_by)->toBe($this->admin->id);
});

it('D-454: in review mode client and season do not change', function () {
    $proposal = rveSubmitted($this->agent);
    $other = Client::factory()->ownedBy($this->agent)->create();
    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id));

    rveSave($this->manager, $proposal, rveBody($proposal, [rveClubData($this->lazio, 90)], ['client_id' => $other->id]))
        ->assertSessionHas('toast.message', 'Durante l\'approvazione cliente e stagione della proposta non cambiano');

    expect($proposal->refresh()->client_id)->not->toBe($other->id)
        ->and(rveRow($proposal, $this->lazio)?->led_seconds_per_match)->toBe(60);
});

it('D-455: the review routes are for admin and manager on a waiting variant; the agent routes stay his', function () {
    $proposal = rveSubmitted($this->agent);

    $this->actingAs($this->agent)->get(route('proposals.review.edit', $proposal))->assertForbidden();
    $this->actingAs($this->manager)
        ->put(route('proposals.update', $proposal), rveBody($proposal, [rveClubData($this->lazio, 90)]))
        ->assertForbidden();

    // Withdrawn by the agent, the group is a draft again: no review mode.
    $this->actingAs($this->agent)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.withdraw', $proposal->proposal_group_id))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->manager)->get(route('proposals.review.edit', $proposal))->assertForbidden();
    rveSave($this->manager, $proposal, rveBody($proposal, [rveClubData($this->lazio, 90)]))->assertForbidden();
});

it('D-457: "Copia su altri club" works in review mode with the lock of the work', function () {
    $proposal = rveSubmitted($this->agent, true);
    $this->actingAs($this->manager)->get(route('proposals.groups.show', $proposal->proposal_group_id));

    $this->actingAs($this->manager)
        ->from(route('proposals.review.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->post(route('proposals.review.copy-clubs', $proposal), [
            'lock_version' => $proposal->refresh()->lock_version,
            'source_club_id' => $this->lazio->id,
            'target_club_ids' => [$this->roma->id],
        ])
        ->assertSessionHasNoErrors();

    expect(rveRow($proposal, $this->roma)?->led_seconds_per_match)->toBe(60)
        ->and($proposal->refresh()->status)->toBe(ProposalStatus::Submitted)
        ->and(ProposalEvent::query()->where('proposal_id', $proposal->id)->where('type', ProposalEventType::EditedInReview->value)->count())->toBe(1);
});
