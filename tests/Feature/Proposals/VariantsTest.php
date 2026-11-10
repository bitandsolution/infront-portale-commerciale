<?php

use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\Services\Settings;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
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
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Variants A…J of a proposal in the same document (D-396; slice DM, PR DM-01): every proposal is a
| variant of a group (a single proposal is variant A); "Aggiungi variante" (proposals.variants.store,
| in the route × role matrix) copies the draft in its group; the variants share client and season.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);

    $this->lazio = varClub($this, 'SS Lazio', 'LAZIO');
    $this->agent = User::factory()->agent()->create();
});

function varClub(object $test, string $name, string $code): Club
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

/** A draft of the agent with Lazio TOP and 1′ of 2nd row, saved as the configurator does. */
function varDraft(User $agent, Club $club, ?Client $client = null): Proposal
{
    $client ??= Client::factory()->ownedBy($agent)->create();
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

function varAdd(User $agent, Proposal $proposal, int $step = 4): TestResponse
{
    return test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => $step]))
        ->post(route('proposals.variants.store', $proposal), ['step' => $step]);
}

/** @return list<string> */
function varLetters(Proposal $proposal): array
{
    /** @var list<string> $letters */
    $letters = Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->orderBy('variant_letter')->pluck('variant_letter')->all();

    return $letters;
}

function varMaxVariants(int $max): void
{
    app(UpdateSettings::class)->handle(['proposal_max_variants' => $max], User::factory()->admin()->create());
    app(Settings::class)->forget();
}

it('D-396: a new draft is variant A of a new group with its agent, client and season', function () {
    $proposal = varDraft($this->agent, $this->lazio);
    $group = ProposalGroup::query()->findOrFail($proposal->proposal_group_id);

    expect($proposal->variant_letter)->toBe('A')
        ->and($group->agent_id)->toBe($this->agent->id)
        ->and($group->client_id)->toBe($proposal->client_id)
        ->and($group->season_id)->toBe($this->season->id);
});

it('D-396: "Aggiungi variante" copies the draft in its group as variant B, with its own number', function () {
    $original = varDraft($this->agent, $this->lazio);

    $response = varAdd($this->agent, $original);

    $variant = Proposal::query()->where('proposal_group_id', $original->proposal_group_id)->where('variant_letter', 'B')->sole();
    $response->assertRedirect(route('proposals.edit', ['proposal' => $variant, 'passo' => 4]))
        ->assertSessionHas('toast.message', 'Variante B creata (proposta n. '.$variant->number.')');

    expect($variant->number)->toBeGreaterThan($original->number)
        ->and($variant->isDraft())->toBeTrue()
        ->and($variant->client_id)->toBe($original->client_id)
        ->and($variant->season_id)->toBe($original->season_id)
        ->and($variant->workingVersion?->starts_on?->toDateString())->toBe($original->workingVersion?->starts_on?->toDateString())
        ->and($variant->workingVersion?->list_total)->toBe($original->workingVersion?->list_total);

    $row = ProposalVersionClub::query()->where('proposal_version_id', $variant->workingVersion?->id)->sole();
    expect($row->club_id)->toBe($this->lazio->id)
        ->and($row->led_seconds_per_match)->toBe(60);

    $event = ProposalEvent::query()->where('proposal_id', $variant->id)->sole();
    expect($event->type)->toBe(ProposalEventType::VariantAdded)
        ->and($event->notes)->toBe('Variante B, copia della proposta n. '.$original->number);

    expect(soleAuditEvent(AuditAction::ProposalVariantAdded)->new_values)->toMatchArray([
        'number' => $variant->number,
        'proposal_group_id' => $original->proposal_group_id,
        'variant_letter' => 'B',
        'duplicated_from_number' => $original->number,
        'club_LAZIO' => 'TOP | FILA_2 1′00″ | —',
    ]);
});

it('D-396: at most the variants of the settings in a group', function () {
    varMaxVariants(2);
    $original = varDraft($this->agent, $this->lazio);
    varAdd($this->agent, $original)->assertSessionHasNoErrors();

    varAdd($this->agent, $original)->assertSessionHas('toast.message', 'Una proposta può avere al massimo 2 varianti');

    expect(varLetters($original))->toBe(['A', 'B']);

    $this->actingAs($this->agent)
        ->get(route('proposals.edit', ['proposal' => $original, 'passo' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.variantLetter', 'A')
            ->has('draft.variants', 2)
            ->where('draft.variants.0.current', true)
            ->where('draft.variants.1.letter', 'B')
            ->where('draft.sharedHeader', true)
            ->where('draft.canAddVariant', false)
            ->where('draft.maxVariants', 2));
});

it('D-396: a new variant takes the first free letter; the others keep theirs', function () {
    $original = varDraft($this->agent, $this->lazio);
    varAdd($this->agent, $original);
    varAdd($this->agent, $original);
    $b = Proposal::query()->where('proposal_group_id', $original->proposal_group_id)->where('variant_letter', 'B')->sole();

    $this->actingAs($this->agent)->delete(route('proposals.destroy', $b))->assertRedirect(PortalPages::url('proposals_mine'));
    expect(varLetters($original))->toBe(['A', 'C']);

    varAdd($this->agent, $original);
    expect(varLetters($original))->toBe(['A', 'B', 'C']);
});

it('D-396: deleted from the configurator, a variant leads to another variant; the last one takes the group with it', function () {
    $original = varDraft($this->agent, $this->lazio);
    varAdd($this->agent, $original);
    $b = Proposal::query()->where('proposal_group_id', $original->proposal_group_id)->where('variant_letter', 'B')->sole();
    $groupId = $original->proposal_group_id;

    $this->actingAs($this->agent)
        ->delete(route('proposals.destroy', $original), ['stay_in_group' => true, 'step' => 3])
        ->assertRedirect(route('proposals.edit', ['proposal' => $b, 'passo' => 3]))
        ->assertSessionHas('toast.message', 'Variante A (proposta n. '.$original->number.') eliminata');

    expect(ProposalGroup::query()->whereKey($groupId)->exists())->toBeTrue();

    $this->actingAs($this->agent)
        ->delete(route('proposals.destroy', $b), ['stay_in_group' => true])
        ->assertRedirect(PortalPages::url('proposals_mine'));

    expect(ProposalGroup::query()->whereKey($groupId)->exists())->toBeFalse();
});

it('D-396: the variants share client and season; the dates of each variant are its own', function () {
    $original = varDraft($this->agent, $this->lazio);
    varAdd($this->agent, $original);
    $other = Client::factory()->ownedBy($this->agent)->create();
    $otherSeason = Season::factory()->forYear(2028)->create();
    $version = $original->workingVersion;
    $save = fn (array $changes) => $this->actingAs($this->agent)
        ->from(route('proposals.edit', ['proposal' => $original, 'passo' => 2]))
        ->put(route('proposals.update', $original), [
            'lock_version' => $original->refresh()->lock_version,
            'client_id' => $original->client_id,
            'season_id' => $original->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            ...$changes,
        ]);
    $message = 'Le varianti di una proposta hanno lo stesso cliente e la stessa stagione: per cambiarli elimina prima le altre varianti';

    $save(['client_id' => $other->id])->assertSessionHas('toast.message', $message);
    $save(['season_id' => $otherSeason->id, 'starts_on' => '2028-07-01', 'ends_on' => '2029-06-30'])->assertSessionHas('toast.message', $message);
    expect($original->refresh()->client_id)->not->toBe($other->id);

    $save(['starts_on' => '2027-10-01'])->assertSessionHasNoErrors();
    expect($original->workingVersion()->first()?->starts_on?->toDateString())->toBe('2027-10-01');
});

it('D-396: alone in its group, a draft changes client and the group follows', function () {
    $proposal = varDraft($this->agent, $this->lazio);
    $other = Client::factory()->ownedBy($this->agent)->create();
    $version = $proposal->workingVersion;

    $this->actingAs($this->agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 1]))
        ->put(route('proposals.update', $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $other->id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect(ProposalGroup::query()->findOrFail($proposal->proposal_group_id)->client_id)->toBe($other->id);
});

it('D-396: another agent cannot add a variant to a draft that is not his', function () {
    $original = varDraft($this->agent, $this->lazio);

    varAdd(User::factory()->agent()->create(), $original)->assertForbidden();

    expect(varLetters($original))->toBe(['A']);
});

it('D-396: "Le mie proposte" keeps the variants of a group together, in the order of their letter', function () {
    $single = varDraft($this->agent, $this->lazio);
    $original = varDraft($this->agent, $this->lazio);
    varAdd($this->agent, $original);

    $this->actingAs($this->agent)
        ->get(PortalPages::url('proposals_mine'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('drafts', 3)
            ->where('drafts.0.number', $original->number)
            ->where('drafts.0.variant', 'A')
            ->where('drafts.0.groupSize', 2)
            ->where('drafts.1.variant', 'B')
            ->where('drafts.2.number', $single->number)
            ->where('drafts.2.variant', null)
            ->where('drafts.2.groupSize', 1));
});

it('D-396: the letter goes from A to J and stays with its group', function () {
    $proposal = Proposal::factory()->withWorkingVersion()->create();
    $update = fn (array $values) => DB::table('proposals')->where('id', $proposal->id)->update($values);

    expect($proposal->variant_letter)->toBe('A')
        ->and(fn () => $update(['variant_letter' => 'K']))->toThrow(QueryException::class)
        ->and(fn () => $update(['variant_letter' => 'b']))->toThrow(QueryException::class)
        ->and(fn () => $update(['variant_letter' => null]))->toThrow(QueryException::class);

    $update(['variant_letter' => 'J']);
    expect($proposal->refresh()->variant_letter)->toBe('J');
});
