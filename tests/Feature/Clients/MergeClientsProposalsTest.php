<?php

use App\Domain\Clients\Actions\MergeClients;
use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
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
use Inertia\Testing\AssertableInertia as Assert;

/*
| Merge of clients with their proposals (M05 F-08; D-359, D-480…D-484; slice F5, PR F5-07): every group
| of the duplicate moves to the client that stays with its variants, in any status, in the same
| transaction; the agent does not change; the frozen versions stay as they are; history, audit and the
| counter in client.merged; an open configurator gets the conflict. A new version made after the merge
| freezes the data of the client that stays (D-481). The concurrency is in
| tests/Concurrency/MergeClientsTest.php.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();
    $this->lazio = Club::factory()->infront()->create(['name' => 'SS Lazio', 'code' => 'LAZIO']);
    ClubEnrollment::query()->create(['competition_season_id' => $league->id, 'club_id' => $this->lazio->id]);
    LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $this->lazio->id, 'led_format_id' => $this->format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'inventory_pool_id' => InventoryPool::query()->where('code', 'FILA_2')->value('id'), 'is_enabled' => true, 'seconds_per_match' => 600]);

    $this->agent = User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Verdi']);
    $this->otherAgent = User::factory()->agent()->create(['first_name' => 'Sara', 'last_name' => 'Rossi']);
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);

    $this->duplicate = Client::factory()->ownedBy($this->agent)->named('Birra Aurora')->create();
    $this->keep = Client::factory()->ownedBy($this->otherAgent)->named('Birra Aurora S.p.A.')->create([
        'logo_file_id' => storeTestFile(FilePurpose::ClientLogo, null, null, 'logo.png')->id,
    ]);
});

function mcpSave(User $actor, Proposal $proposal, int $seconds, string $route = 'proposals.update', string $page = 'proposals.edit'): void
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    test()->actingAs($actor)
        ->from(route($page, ['proposal' => $proposal, 'passo' => 4]))
        ->put(route($route, $proposal), mcpPayload($proposal, $version, $seconds))
        ->assertSessionHasNoErrors();
}

/** @return array<string, mixed> */
function mcpPayload(Proposal $proposal, ?ProposalVersion $version, int $seconds): array
{
    return [
        'lock_version' => $proposal->lock_version,
        'client_id' => $proposal->client_id,
        'season_id' => $proposal->season_id,
        'starts_on' => $version?->starts_on?->toDateString(),
        'ends_on' => $version?->ends_on?->toDateString(),
        'clubs' => [[
            'club_id' => test()->lazio->id,
            'partnership_tier_id' => null,
            'led_format_id' => test()->format->id,
            'led_seconds' => $seconds,
            'seats' => [],
        ]],
    ];
}

function mcpDraft(object $test, Client $client, int $seconds = 60): Proposal
{
    test()->actingAs($test->agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    mcpSave($test->agent, $proposal, $seconds);

    return $proposal->refresh();
}

/** A group of the client with the variants A and B, approved by the admin. */
function mcpApproved(object $test, Client $client): Proposal
{
    $first = mcpDraft($test, $client);
    test()->actingAs($test->agent)
        ->from(route('proposals.edit', ['proposal' => $first, 'passo' => 5]))
        ->post(route('proposals.variants.store', $first), ['step' => 5])
        ->assertSessionHasNoErrors();
    mcpSave($test->agent, Proposal::query()->where('proposal_group_id', $first->proposal_group_id)->latest('id')->firstOrFail(), 120);
    test()->actingAs($test->agent)->post(route('proposals.groups.submit', $first->proposal_group_id))->assertSessionHasNoErrors();
    test()->actingAs($test->admin)
        ->from(route('proposals.groups.show', $first->proposal_group_id))
        ->post(route('proposals.groups.approve', $first->proposal_group_id))
        ->assertSessionHasNoErrors();

    return $first->refresh();
}

function mcpMerge(object $test, Client $duplicate, Client $keep): void
{
    test()->actingAs($test->admin)
        ->from(route('clients.edit', $duplicate))
        ->post(route('clients.merge', $duplicate), ['keep_id' => (string) $keep->id])
        ->assertSessionHasNoErrors();
}

it('D-480: the merge moves every group of the duplicate, in any status, keeping agent and frozen versions', function () {
    $approved = mcpApproved($this, $this->duplicate);
    $variantB = Proposal::query()->where('proposal_group_id', $approved->proposal_group_id)->where('variant_letter', 'B')->firstOrFail();
    $draft = mcpDraft($this, $this->duplicate);
    $frozen = ProposalVersion::query()->whereNotNull('number')->orderBy('id')->get(['id', 'client_name', 'client_logo_file_id', 'updated_at'])->toArray();
    $commitments = InventoryCommitment::query()->orderBy('id')->get()->toArray();
    $lockVersions = Proposal::query()->orderBy('id')->pluck('lock_version', 'id')->all();

    mcpMerge($this, $this->duplicate, $this->keep);

    expect(ProposalGroup::query()->pluck('client_id')->unique()->all())->toBe([$this->keep->id])
        ->and(Proposal::query()->pluck('client_id')->unique()->all())->toBe([$this->keep->id])
        ->and(Proposal::query()->pluck('agent_id')->unique()->all())->toBe([$this->agent->id])
        ->and(ProposalGroup::query()->pluck('agent_id')->unique()->all())->toBe([$this->agent->id])
        ->and($approved->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and($draft->refresh()->status)->toBe(ProposalStatus::Draft)
        // The frozen versions keep the name of the duplicate (the PDF presented to the client).
        ->and(ProposalVersion::query()->whereNotNull('number')->orderBy('id')->get(['id', 'client_name', 'client_logo_file_id', 'updated_at'])->toArray())->toBe($frozen)
        ->and(collect($frozen)->pluck('client_name')->unique()->all())->toBe(['Birra Aurora'])
        ->and(InventoryCommitment::query()->orderBy('id')->get()->toArray())->toBe($commitments);

    // D-484: every moved variant has a new lock_version.
    foreach (Proposal::query()->orderBy('id')->get() as $proposal) {
        expect($proposal->lock_version)->toBe($lockVersions[$proposal->id] + 1);
    }

    // D-482: history of every variant, one audit event per group, the counter in client.merged.
    $history = ProposalEvent::query()->where('type', ProposalEventType::ClientMerged->value)->orderBy('proposal_id')->get();
    expect($history->pluck('proposal_id')->all())->toBe([$approved->id, $variantB->id, $draft->id])
        ->and($history->first()?->notes)->toBe('Cliente unito: Birra Aurora → Birra Aurora S.p.A.')
        ->and($history->first()?->user_id)->toBe($this->admin->id);

    $events = AuditEvent::query()->where('action', AuditAction::ProposalClientMerged->value)->orderBy('id')->get();
    expect($events)->toHaveCount(2)
        ->and($events->first()?->object_id)->toBe($approved->id)
        ->and($events->first()?->old_values)->toBe(['client_id' => $this->duplicate->id, 'client_name' => 'Birra Aurora'])
        ->and($events->first()?->new_values)->toBe([
            'client_id' => $this->keep->id,
            'client_name' => 'Birra Aurora S.p.A.',
            'proposal_group_id' => $approved->proposal_group_id,
            'variant_letters' => 'A, B',
        ])
        ->and(AuditEvent::query()->where('action', AuditAction::ClientMerged->value)->sole()->new_values['moved_groups'] ?? null)->toBe(2);

    // The agent still sees and opens his proposals, now of the client that stays (D-483).
    $this->actingAs($this->agent)->get(route('proposals.groups.show', $approved->proposal_group_id))
        ->assertInertia(fn (Assert $page) => $page->where('group.client', 'Birra Aurora S.p.A.'));
});

it('D-480: the groups of a client merged earlier into the duplicate move too', function () {
    $earlier = Client::factory()->ownedBy($this->agent)->named('Aurora Birra')->create();
    $draft = mcpDraft($this, $earlier);
    // Rows left by a merge made before F5-07: the client is merged, its proposals are still on it.
    $earlier->forceFill(['is_active' => false, 'merged_into_id' => $this->duplicate->id])->save();

    mcpMerge($this, $this->duplicate, $this->keep);

    expect($draft->refresh()->client_id)->toBe($this->keep->id)
        ->and($earlier->refresh()->merged_into_id)->toBe($this->keep->id)
        ->and(AuditEvent::query()->where('action', AuditAction::ClientMerged->value)->where('object_id', $earlier->id)->sole()->new_values['moved_groups'] ?? null)->toBe(1)
        ->and(AuditEvent::query()->where('action', AuditAction::ClientMerged->value)->where('object_id', $this->duplicate->id)->sole()->new_values)->not->toHaveKey('moved_groups');
});

it('D-484: an open configurator gets the conflict; after reloading, the agent saves the moved draft', function () {
    $draft = mcpDraft($this, $this->duplicate);
    $stale = mcpPayload($draft, $draft->workingVersion, 90);

    mcpMerge($this, $this->duplicate, $this->keep);

    $this->actingAs($this->agent)
        ->from(route('proposals.edit', ['proposal' => $draft, 'passo' => 4]))
        ->put(route('proposals.update', $draft), $stale)
        ->assertSessionHasErrors(['conflict' => __('proposals.validation.conflict')]);

    $this->actingAs($this->agent)->get(route('proposals.edit', $draft))
        ->assertInertia(fn (Assert $page) => $page->where('draft.lockVersion', $draft->refresh()->lock_version));

    // The client that stays is of another agent: the draft keeps it and is still saved (D-483).
    mcpSave($this->agent, $draft, 90);
    expect($draft->refresh()->client_id)->toBe($this->keep->id)
        ->and($draft->workingVersion?->clubs()->value('led_seconds_per_match'))->toBe(90);
});

it('D-481: a new version after the merge freezes name and logo of the client that stays', function () {
    $proposal = mcpApproved($this, $this->duplicate);
    mcpMerge($this, $this->duplicate, $this->keep);

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.revision.start', $proposal))
        ->assertSessionHasNoErrors();
    mcpSave($this->admin, $proposal, 90, 'proposals.revision.update', 'proposals.revision.edit');
    $this->actingAs($this->admin)
        ->from(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->post(route('proposals.revision.save', $proposal), ['lock_version' => $proposal->refresh()->lock_version])
        ->assertSessionHasNoErrors();

    $versions = ProposalVersion::query()->where('proposal_id', $proposal->id)->whereNotNull('number')->orderBy('number')->get();
    expect($versions->pluck('client_name')->all())->toBe(['Birra Aurora', 'Birra Aurora S.p.A.'])
        ->and($versions->last()?->client_logo_file_id)->toBe($this->keep->logo_file_id)
        ->and($versions->last()?->commodity_sector_id)->toBe($this->keep->commodity_sector_id);
});

it('D-483: the merge dialog knows the groups that move, their agents and the agent of each client that stays', function () {
    mcpDraft($this, $this->duplicate);
    mcpApproved($this, $this->duplicate);

    $this->actingAs($this->admin)->get(route('clients.edit', $this->duplicate))
        ->assertInertia(fn (Assert $page) => $page
            ->where('management.mergeGroups', 2)
            ->where('management.mergeAgentIds', [(string) $this->agent->id])
            ->where('management.mergeTargets', fn ($targets) => collect($targets)->contains(fn (array $target): bool => $target['value'] === (string) $this->keep->id && $target['ownerId'] === (string) $this->otherAgent->id)));

    $this->actingAs($this->admin)->get(route('clients.edit', $this->keep))
        ->assertInertia(fn (Assert $page) => $page->where('management.mergeGroups', 0)->where('management.mergeAgentIds', []));
});

it('D-480: a merge without proposals works as before', function () {
    app(MergeClients::class)->handle($this->duplicate, $this->keep, $this->admin);

    expect($this->duplicate->refresh()->merged_into_id)->toBe($this->keep->id)
        ->and(AuditEvent::query()->where('action', AuditAction::ProposalClientMerged->value)->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', AuditAction::ClientMerged->value)->sole()->new_values)->not->toHaveKey('moved_groups');
});
