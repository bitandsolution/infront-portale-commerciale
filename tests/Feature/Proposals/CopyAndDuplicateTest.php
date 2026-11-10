<?php

use App\Domain\Proposals\Actions\DuplicateProposal;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\HospitalityCapacity;
use App\Models\HospitalityPrice;
use App\Models\HospitalityVariant;
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
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Support functions of the configurator (M06 §5, §9; M06-CA7, M06-CA8; D-091, D-372, D-377; slice
| F4-05): "Copia su altri club" (proposals.copy-clubs) and "Duplica proposta" (proposals.duplicate,
| from "Le mie proposte"). Both routes are in the route × role matrix.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);

    // Lazio, the club copied: TOP partnership, 2nd row 10′ per match, Premium and Base seats.
    $this->lazio = dupClub($this, 'SS Lazio', 'LAZIO');
    dupLed($this, $this->lazio, 'FILA_2', '1000.00', 600);
    dupTier($this, $this->lazio, 'TOP', '180000.00', 1);
    dupSeats($this, $this->lazio, 'PREMIUM', '1500.00', 300);

    // Torino: everything Lazio has, at other prices.
    $this->torino = dupClub($this, 'Torino FC', 'TORINO');
    dupLed($this, $this->torino, 'FILA_2', '800.00', 600);
    dupTier($this, $this->torino, 'TOP', '120000.00', 1);
    dupSeats($this, $this->torino, 'PREMIUM', '900.00', 100);

    // Roma: 2nd row only; no TOP partnership and no hospitality on its list.
    $this->roma = dupClub($this, 'AS Roma', 'ROMA');
    dupLed($this, $this->roma, 'FILA_2', '1200.00', 600);
    dupTier($this, $this->roma, 'PREMIUM', '90000.00', 1);

    // Bologna: TOP and Premium seats, but only 30″ of 2nd row left per match.
    $this->bologna = dupClub($this, 'Bologna FC', 'BOLOGNA');
    dupLed($this, $this->bologna, 'FILA_2', '700.00', 30);
    dupTier($this, $this->bologna, 'TOP', '100000.00', 1);
    dupSeats($this, $this->bologna, 'PREMIUM', '800.00', 50);

    $this->agent = User::factory()->agent()->create();
});

function dupClub(object $test, string $name, string $code): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, 'code' => $code]);
    ClubEnrollment::query()->create(['competition_season_id' => $test->league->id, 'club_id' => $club->id]);

    return $club;
}

function dupFormat(string $code): LedFormat
{
    return LedFormat::query()->where('code', $code)->firstOrFail();
}

function dupTierModel(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function dupVariant(string $code): HospitalityVariant
{
    return HospitalityVariant::query()->where('code', $code)->firstOrFail();
}

function dupLed(object $test, Club $club, string $code, string $price, int $seconds): void
{
    LedPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'led_format_id' => dupFormat($code)->id, 'unit_price' => $price]);
    PoolCapacity::query()->create([
        'season_id' => $test->season->id,
        'club_id' => $club->id,
        'inventory_pool_id' => InventoryPool::query()->where('code', $code)->firstOrFail()->id,
        'is_enabled' => true,
        'seconds_per_match' => $seconds,
    ]);
}

function dupTier(object $test, Club $club, string $code, string $price, int $quantity): void
{
    PartnershipPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'partnership_tier_id' => dupTierModel($code)->id, 'season_price' => $price]);
    PartnershipCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'partnership_tier_id' => dupTierModel($code)->id, 'is_enabled' => true, 'quantity' => $quantity]);
}

function dupSeats(object $test, Club $club, string $code, string $price, int $seats): void
{
    HospitalityPrice::query()->create(['price_list_id' => $test->priceList->id, 'club_id' => $club->id, 'hospitality_variant_id' => dupVariant($code)->id, 'seat_price' => $price]);
    HospitalityCapacity::query()->create(['season_id' => $test->season->id, 'club_id' => $club->id, 'hospitality_variant_id' => dupVariant($code)->id, 'is_enabled' => true, 'seats' => $seats]);
}

/**
 * A draft of the agent with the given clubs, saved as the configurator does.
 *
 * @param  list<array<string, mixed>>  $clubs
 */
function dupDraft(User $agent, array $clubs, ?Client $client = null): Proposal
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
            'clubs' => $clubs,
        ])
        ->assertSessionHasNoErrors();

    return $proposal->refresh();
}

/**
 * @param  array<string, int>  $seats  seats by variant code
 * @return array<string, mixed>
 */
function dupRow(Club $club, ?string $tier = null, ?string $format = null, int $seconds = 0, array $seats = []): array
{
    return [
        'club_id' => $club->id,
        'partnership_tier_id' => $tier !== null ? dupTierModel($tier)->id : null,
        'led_format_id' => $format !== null ? dupFormat($format)->id : null,
        'led_seconds' => $seconds,
        'seats' => array_map(fn (string $code, int $count): array => ['variant_id' => dupVariant($code)->id, 'seats' => $count], array_keys($seats), array_values($seats)),
    ];
}

/**
 * @param  list<Club>  $targets
 */
function dupCopy(User $agent, Proposal $proposal, Club $source, array $targets, ?int $lockVersion = null): TestResponse
{
    return test()->actingAs($agent)
        ->from(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->post(route('proposals.copy-clubs', $proposal), [
            'lock_version' => $lockVersion ?? $proposal->refresh()->lock_version,
            'source_club_id' => $source->id,
            'target_club_ids' => array_map(fn (Club $club): int => $club->id, $targets),
        ]);
}

function dupRowOf(Proposal $proposal, Club $club): ?ProposalVersionClub
{
    return ProposalVersionClub::query()
        ->where('proposal_version_id', $proposal->workingVersion()->value('id'))
        ->where('club_id', $club->id)
        ->with('seats')
        ->first();
}

/** Seats of a row by variant code. */
function dupSeatsOf(?ProposalVersionClub $row): array
{
    $seats = [];

    foreach ($row?->seats ?? [] as $seat) {
        $seats[dupVariantCode($seat->hospitality_variant_id)] = $seat->seats;
    }

    return $seats;
}

function dupVariantCode(int $id): string
{
    return (string) HospitalityVariant::query()->whereKey($id)->value('code');
}

/* "Copia su altri club" (M06 §5, M06-CA7). */

it('M06-CA7: the configuration is copied on the compatible clubs and the unavailable options are reported club by club', function () {
    $proposal = dupDraft($this->agent, [
        dupRow($this->lazio, 'TOP', 'FILA_2', 60, ['PREMIUM' => 2]),
        dupRow($this->torino),
        dupRow($this->roma, 'PREMIUM'),
        dupRow($this->bologna, null, 'FILA_2', 30),
    ]);
    $lockVersion = $proposal->lock_version;

    dupCopy($this->agent, $proposal, $this->lazio, [$this->torino, $this->roma, $this->bologna])
        ->assertRedirect(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Configurazione copiata su 3 club')
        ->assertSessionHas('proposal_copied', function (array $copied): bool {
            $skipped = collect($copied['skipped'])->keyBy('club');

            return $copied['copied'] === 'Copiata su: Torino FC, AS Roma, Bologna FC.'
                && ! $skipped->has('Torino FC')
                && count($skipped['AS Roma']['items']) === 2
                && str_contains($skipped['AS Roma']['items'][0], 'pacchetto '.dupTierModel('TOP')->name)
                && str_contains($skipped['AS Roma']['items'][1], dupVariant('PREMIUM')->name)
                && $skipped['Bologna FC']['items'] === ['I minuti vanno a passi di 0′30″ e non possono superare il residuo (0′30″).'];
        });

    // Torino: everything copied, priced on its own list.
    $torino = dupRowOf($proposal, $this->torino);
    expect($torino?->partnership_tier_id)->toBe(dupTierModel('TOP')->id)
        ->and($torino?->led_format_id)->toBe(dupFormat('FILA_2')->id)
        ->and($torino?->led_seconds_per_match)->toBe(60)
        ->and(dupSeatsOf($torino))->toBe(['PREMIUM' => 2])
        ->and($torino?->club_total)->toBe('137000.00'); // 120.000 + 800 × 1 × 19 + 900 × 2

    // Roma: the LED is copied; TOP and the Premium seats are skipped, its own package stays.
    $roma = dupRowOf($proposal, $this->roma);
    expect($roma?->partnership_tier_id)->toBe(dupTierModel('PREMIUM')->id)
        ->and($roma?->led_seconds_per_match)->toBe(60)
        ->and(dupSeatsOf($roma))->toBe([]);

    // Bologna: 1′ beyond the residual of 30″ is skipped, its 30″ stay; TOP and seats are copied.
    $bologna = dupRowOf($proposal, $this->bologna);
    expect($bologna?->partnership_tier_id)->toBe(dupTierModel('TOP')->id)
        ->and($bologna?->led_seconds_per_match)->toBe(30)
        ->and(dupSeatsOf($bologna))->toBe(['PREMIUM' => 2]);

    // Saved like an automatic save: lock version + 1, one event with the changed clubs (D-372).
    expect($proposal->refresh()->lock_version)->toBe($lockVersion + 1);
    $event = auditEventsOf(AuditAction::ProposalDraftUpdated)->last();
    expect($event?->new_values)->toMatchArray([
        'club_TORINO' => 'TOP | FILA_2 1′00″ | PREMIUM 2',
        'club_ROMA' => 'PREMIUM | FILA_2 1′00″ | —',
        'club_BOLOGNA' => 'TOP | FILA_2 0′30″ | PREMIUM 2',
    ])->and($event?->new_values)->not->toHaveKey('club_LAZIO');

    // The page shows the outcome until it is closed.
    $this->actingAs($this->agent)
        ->withSession(['proposal_copied' => ['copied' => 'Copiata su: Torino FC.', 'skipped' => []]])
        ->get(route('proposals.edit', ['proposal' => $proposal, 'passo' => 4]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('copied.copied', 'Copiata su: Torino FC.')
            ->where('draft.copyUrl', route('proposals.copy-clubs', $proposal)));
});

it('M06 §5: the copy replaces the configuration of the targets: what the source lacks is taken away', function () {
    $proposal = dupDraft($this->agent, [
        dupRow($this->lazio, null, 'FILA_2', 30),
        dupRow($this->torino, 'TOP', null, 0, ['PREMIUM' => 3]),
    ]);

    dupCopy($this->agent, $proposal, $this->lazio, [$this->torino])->assertSessionHasNoErrors();

    $torino = dupRowOf($proposal, $this->torino);
    expect($torino?->partnership_tier_id)->toBeNull()
        ->and($torino?->led_seconds_per_match)->toBe(30)
        ->and(dupSeatsOf($torino))->toBe([]);
});

it('D-216: a copy from a page with an old lock version is refused and nothing changes', function () {
    $proposal = dupDraft($this->agent, [dupRow($this->lazio, 'TOP'), dupRow($this->torino)]);

    dupCopy($this->agent, $proposal, $this->lazio, [$this->torino], $proposal->lock_version - 1)
        ->assertSessionHasErrors('conflict');

    expect(dupRowOf($proposal, $this->torino)?->partnership_tier_id)->toBeNull();
});

it('M06 §5: a copy needs a club of the draft and another club of the draft to copy it on', function () {
    $proposal = dupDraft($this->agent, [dupRow($this->lazio, 'TOP'), dupRow($this->torino)]);
    $events = auditEventsOf(AuditAction::ProposalDraftUpdated)->count();

    dupCopy($this->agent, $proposal, $this->lazio, [$this->roma])
        ->assertSessionHas('toast.message', 'Scegli il club da copiare e almeno un altro club della proposta');
    dupCopy($this->agent, $proposal, $this->roma, [$this->torino])
        ->assertSessionHas('toast.message', 'Scegli il club da copiare e almeno un altro club della proposta');

    expect(auditEventsOf(AuditAction::ProposalDraftUpdated))->toHaveCount($events);
});

/* "Duplica proposta" (D-091, M06-CA8, M06 §9). */

it('M06-CA8, D-091: "Duplica" makes a new draft for another client with dates, prices and residuals recalculated', function () {
    $original = dupDraft($this->agent, [
        dupRow($this->lazio, 'TOP', 'FILA_2', 60, ['PREMIUM' => 2]),
        dupRow($this->torino, null, 'FILA_2', 30),
    ]);
    $originalTotal = $original->workingVersion()->value('list_total');
    $other = Client::factory()->ownedBy($this->agent)->create(['company_name' => 'Altro Cliente Srl']);

    // The season has started meanwhile and the list of Lazio changed.
    $this->travelTo(Carbon::parse('2027-10-15 10:00', 'Europe/Rome'));
    LedPrice::query()->where('club_id', $this->lazio->id)->update(['unit_price' => '1100.00']);

    $response = $this->actingAs($this->agent)
        ->post(route('proposals.duplicate', $original), ['client_id' => $other->id]);

    $copy = Proposal::query()->where('duplicated_from_id', $original->id)->sole();
    $response->assertRedirect(route('proposals.edit', ['proposal' => $copy, 'passo' => 4]))
        ->assertSessionHas('toast.message', 'Proposta n. '.$copy->number.' creata come copia della n. '.$original->number)
        ->assertSessionHas('proposal_duplicated', function (array $notice) use ($original): bool {
            return $notice['title'] === 'Copia della proposta n. '.$original->number
                && $notice['prices'] !== null
                && count($notice['prices']['clubs']) === 2
                && str_contains($notice['prices']['clubs'][0]['text'], 'listino aggiornato e partite nel periodo cambiate')
                && $notice['invalidClubs'] === null;
        });

    $version = $copy->workingVersion;
    expect($copy->number)->toBeGreaterThan($original->number)
        ->and($copy->agent_id)->toBe($this->agent->id)
        ->and($copy->client_id)->toBe($other->id)
        ->and($copy->season_id)->toBe($this->season->id)
        ->and($copy->isDraft())->toBeTrue()
        ->and($version?->starts_on?->toDateString())->toBe('2027-10-15')
        ->and($version?->ends_on?->toDateString())->toBe('2028-06-30');

    $lazio = dupRowOf($copy, $this->lazio);
    expect($lazio?->partnership_tier_id)->toBe(dupTierModel('TOP')->id)
        ->and($lazio?->led_seconds_per_match)->toBe(60)
        ->and(dupSeatsOf($lazio))->toBe(['PREMIUM' => 2])
        ->and($lazio?->home_matches)->toBeLessThan(19)
        ->and($lazio?->led_unit_price)->toBe('1100.00')
        ->and($version?->list_total)->not->toBe($originalTotal)
        ->and(dupRowOf($copy, $this->torino)?->led_seconds_per_match)->toBe(30);

    // The original does not change.
    expect($original->refresh()->client_id)->not->toBe($other->id)
        ->and($original->workingVersion()->value('list_total'))->toBe($originalTotal);

    // History "duplicated" with the origin (D-199) and its own audit event (D-372).
    $event = ProposalEvent::query()->where('proposal_id', $copy->id)->sole();
    expect($event->type)->toBe(ProposalEventType::Duplicated)
        ->and($event->notes)->toBe('Copia della proposta n. '.$original->number)
        ->and($event->user_id)->toBe($this->agent->id);

    $audit = soleAuditEvent(AuditAction::ProposalDraftDuplicated);
    expect($audit->object_label)->toBe('Proposta n. '.$copy->number)
        ->and($audit->old_values)->toBeNull()
        ->and($audit->new_values)->toMatchArray([
            'number' => $copy->number,
            'client_name' => 'Altro Cliente Srl',
            'season_code' => '2027-28',
            'starts_on' => '2027-10-15',
            'duplicated_from_id' => $original->id,
            'duplicated_from_number' => $original->number,
            'club_LAZIO' => 'TOP | FILA_2 1′00″ | PREMIUM 2',
            'club_TORINO' => '— | FILA_2 0′30″ | —',
            'list_total' => $version?->list_total,
        ]);
});

it('M06 §9: the copy keeps the clubs no longer valid with their errors and reports them', function () {
    $original = dupDraft($this->agent, [dupRow($this->lazio, 'TOP'), dupRow($this->torino, null, 'FILA_2', 60)]);
    PoolCapacity::query()->where('club_id', $this->torino->id)->update(['is_enabled' => false]);

    $this->actingAs($this->agent)
        ->post(route('proposals.duplicate', $original), ['client_id' => $original->client_id])
        ->assertSessionHas('proposal_duplicated.invalidClubs', 'Da correggere al passo 4: Torino FC.');

    $copy = Proposal::query()->where('duplicated_from_id', $original->id)->sole();
    expect(dupRowOf($copy, $this->torino)?->led_format_id)->toBe(dupFormat('FILA_2')->id);

    $this->actingAs($this->agent)
        ->get(route('proposals.edit', ['proposal' => $copy, 'passo' => 4]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.clubs.1.name', 'Torino FC')
            ->has('draft.clubs.1.errors.led_format'));
});

it('M05 F-02, D-366: the copy is only for an active, not merged client of the agent', function () {
    $original = dupDraft($this->agent, [dupRow($this->lazio, 'TOP')]);
    $count = Proposal::query()->count();
    $inactive = Client::factory()->ownedBy($this->agent)->create(['is_active' => false]);
    $foreign = Client::factory()->create();

    foreach ([$inactive, $foreign] as $client) {
        $this->actingAs($this->agent)
            ->from(PortalPages::url('proposals_mine'))
            ->post(route('proposals.duplicate', $original), ['client_id' => $client->id])
            ->assertRedirect(PortalPages::url('proposals_mine'))
            ->assertSessionHas('toast.message', 'Scegli uno dei tuoi clienti attivi');
    }

    expect(Proposal::query()->count())->toBe($count);
});

it('RB-14: a proposal of another agent is not duplicated, neither by the page nor by the Action', function () {
    $original = dupDraft($this->agent, [dupRow($this->lazio, 'TOP')]);
    $other = User::factory()->agent()->create();
    $client = Client::factory()->ownedBy($other)->create();

    $this->actingAs($other)
        ->post(route('proposals.duplicate', $original), ['client_id' => $client->id])
        ->assertForbidden();

    expect(fn () => app(DuplicateProposal::class)->handle($original, $other, $client))
        ->toThrow(ProposalsActionNotAllowed::class);
});

it('D-377: the original can be deleted after the copy; the copy loses only the reference', function () {
    $original = dupDraft($this->agent, [dupRow($this->lazio, 'TOP')]);
    $this->actingAs($this->agent)->post(route('proposals.duplicate', $original), ['client_id' => $original->client_id]);
    $copy = Proposal::query()->where('duplicated_from_id', $original->id)->sole();

    $this->actingAs($this->agent)->delete(route('proposals.destroy', $original))->assertSessionHasNoErrors();

    expect($copy->refresh()->duplicated_from_id)->toBeNull()
        ->and(dupRowOf($copy, $this->lazio))->not->toBeNull();
});

it('D-374: "Le mie proposte" offers "Duplica" with the agent\'s clients', function () {
    $client = Client::factory()->ownedBy($this->agent)->create(['company_name' => 'Cliente Uno Srl']);
    $draft = dupDraft($this->agent, [dupRow($this->lazio, 'TOP')], $client);

    $this->actingAs($this->agent)
        ->get(PortalPages::url('proposals_mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('drafts.0.duplicateUrl', route('proposals.duplicate', $draft))
            ->where('drafts.0.clientId', (string) $client->id)
            ->where('clients.0.label', 'Cliente Uno Srl'));
});
