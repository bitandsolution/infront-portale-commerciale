<?php

use App\Domain\Inventory\Actions\SetClubCapacities;
use App\Domain\Inventory\Data\CapacityInput;
use App\Domain\Inventory\Exceptions\CapacityBelowCommitted;
use App\Domain\Inventory\Exceptions\InventoryActionNotAllowed;
use App\Domain\Inventory\Services\InventoryAvailability;
use App\Enums\AuditAction;
use App\Enums\AuditArea;
use App\Enums\InventoryResource;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\HospitalityCapacity;
use App\Models\HospitalityVariant;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\Season;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Capacities of the inventory and residuals (M04 F-01…F-03, F-09, RB-01, RB-08, RB-10, RB-12;
| M04-CA10; D-062, D-063, D-066, D-072, D-191, D-192, D-337; F2-04). Permissions of every route are
| in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->season = Season::factory()->open()->create();
    $this->club = inventoryClub($this->season, 'SS Lazio', ['code' => 'LAZIO']);
});

function inventoryClub(Season $season, string $name, array $state = []): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, ...$state]);
    ClubEnrollment::query()->create([
        'competition_season_id' => CompetitionSeason::factory()->create(['season_id' => $season->id])->id,
        'club_id' => $club->id,
    ]);

    return $club;
}

function poolOf(string $code): InventoryPool
{
    return InventoryPool::query()->where('code', $code)->firstOrFail();
}

function tierOf(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function variantOf(string $code): HospitalityVariant
{
    return HospitalityVariant::query()->where('code', $code)->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function capacityForm(Season $season, array $groups, bool $confirmed = false): array
{
    return ['season_id' => $season->id, 'confirmed' => $confirmed, ...$groups];
}

it('M04 F-01: the admin sets the LED minutes per match of a pool, saved in seconds, with an audit event', function () {
    $row1 = poolOf('FILA_1');

    $this->actingAs($this->admin)
        ->put(route('inventory.capacities.update', $this->club), capacityForm($this->season, ['pools' => [$row1->id => ['enabled' => true, 'minutes' => '28,5']]]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Capacità di SS Lazio salvate');

    $capacity = PoolCapacity::query()->sole();
    expect($capacity->is_enabled)->toBeTrue()
        ->and($capacity->seconds_per_match)->toBe(1710);

    $event = soleAuditEvent(AuditAction::PoolCapacityUpdated);
    expect($event->area)->toBe(AuditArea::Inventory->value)
        ->and($event->object_label)->toBe("SS Lazio (LAZIO) · {$this->season->code} · 1ª fila (FILA_1)")
        ->and($event->old_values)->toBe([])
        ->and($event->new_values)->toBe(['is_enabled' => true, 'seconds_per_match' => 1710]);
});

it('M04 F-02, F-03: the admin sets partnership contracts per tier and hospitality seats per variant', function () {
    $this->actingAs($this->admin)
        ->put(route('inventory.capacities.update', $this->club), capacityForm($this->season, [
            'tiers' => [tierOf('PREMIUM')->id => ['enabled' => true, 'quantity' => 3]],
            'variants' => [variantOf('BASE')->id => ['enabled' => true, 'seats' => 40]],
        ]))
        ->assertSessionHasNoErrors();

    expect(PartnershipCapacity::query()->sole()->quantity)->toBe(3)
        ->and(HospitalityCapacity::query()->sole()->seats)->toBe(40)
        ->and(soleAuditEvent(AuditAction::PartnershipCapacityUpdated)->new_values)->toBe(['is_enabled' => true, 'quantity' => 3])
        ->and(soleAuditEvent(AuditAction::HospitalityCapacityUpdated)->new_values)->toBe(['is_enabled' => true, 'seats' => 40]);
});

it('D-066: a resource not managed is kept apart from a managed resource at 0', function () {
    $pool = poolOf('FILA_2');
    app(SetClubCapacities::class)->handle($this->season, $this->club, [new CapacityInput(InventoryResource::Pool, $pool->id, true, 0)], false, $this->admin);

    $items = collect(app(InventoryAvailability::class)->forClub($this->season, $this->club)['pool'])->keyBy('code');

    expect($items['FILA_2']->isConfigured)->toBeTrue()
        ->and($items['FILA_2']->isEnabled)->toBeTrue()
        ->and($items['FILA_2']->remaining())->toBe(0)
        ->and($items['FILA_1']->isConfigured)->toBeFalse()
        ->and($items['FILA_1']->isEnabled)->toBeFalse();
});

it('only changed rows are written: a second identical save changes nothing', function () {
    $form = capacityForm($this->season, ['pools' => [poolOf('FILA_1')->id => ['enabled' => true, 'minutes' => '30']]]);
    $this->actingAs($this->admin)->put(route('inventory.capacities.update', $this->club), $form);

    $this->actingAs($this->admin)
        ->put(route('inventory.capacities.update', $this->club), $form)
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');

    expect(AuditEvent::query()->where('action', AuditAction::PoolCapacityUpdated->value)->count())->toBe(1);
});

it('D-063: LED minutes go at steps of 30″ and within the length of a match', function (string $minutes, string $message) {
    $this->actingAs($this->admin)
        ->put(route('inventory.capacities.update', $this->club), capacityForm($this->season, ['pools' => [poolOf('FILA_1')->id => ['enabled' => true, 'minutes' => $minutes]]]))
        ->assertSessionHasErrors(['pools.'.poolOf('FILA_1')->id.'.minutes' => $message]);

    expect(PoolCapacity::query()->count())->toBe(0);
})->with([
    'not on the step' => ['6,25', 'I minuti vanno a passi di 0′30″'],
    'not a number' => ['sei', 'Scrivi i minuti a partita, per esempio 6,5 per 6′30″'],
    'beyond a match' => ['90,5', 'Al massimo 90′00″ a partita'],
]);

it('D-063: the Action refuses seconds off the step, whoever calls it', function () {
    expect(fn () => app(SetClubCapacities::class)->handle($this->season, $this->club, [new CapacityInput(InventoryResource::Pool, poolOf('FILA_1')->id, true, 45)], false, $this->admin))
        ->toThrow(InventoryActionNotAllowed::class, 'La capacità di 1ª fila va a passi di 0′30″');
});

it('D-052: the free package and inactive pools have no capacity', function (Closure $input) {
    expect(fn () => app(SetClubCapacities::class)->handle($this->season, $this->club, [$input()], false, $this->admin))
        ->toThrow(InventoryActionNotAllowed::class)
        ->and(PartnershipCapacity::query()->count() + PoolCapacity::query()->count())->toBe(0);
})->with([
    'Libera' => [fn () => new CapacityInput(InventoryResource::Partnership, tierOf('LIBERA')->id, true, 5)],
    'inactive pool' => [fn () => new CapacityInput(InventoryResource::Pool, poolOf('CORNER_BASSI')->id, true, 60)],
]);

it('RB-01, RB-12: residual = capacity − open commitments, released ones excluded, whatever the contract period', function () {
    $pool = poolOf('FILA_1');
    app(SetClubCapacities::class)->handle($this->season, $this->club, [new CapacityInput(InventoryResource::Pool, $pool->id, true, 1800)], false, $this->admin);

    InventoryCommitment::factory()->forPool($pool, 600)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);
    InventoryCommitment::factory()->forPool($pool, 300)->create([
        'season_id' => $this->season->id,
        'club_id' => $this->club->id,
        'starts_on' => $this->season->starts_on->copy()->addMonths(6)->toDateString(),
    ]);
    $released = InventoryCommitment::factory()->forPool($pool, 900)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);
    DB::table('inventory_commitments')->where('id', $released->id)->update(['released_at' => now(), 'released_by' => $this->admin->id, 'release_reason' => 'superseded']);
    // Another club and another season do not count.
    InventoryCommitment::factory()->forPool($pool, 1200)->create(['season_id' => $this->season->id]);

    $item = collect(app(InventoryAvailability::class)->forClub($this->season, $this->club)['pool'])->firstWhere('code', 'FILA_1');

    expect($item->capacity)->toBe(1800)
        ->and($item->committed)->toBe(900)
        ->and($item->remaining())->toBe(900);
});

it('M04-CA10, RB-10: a capacity under the committed asks for confirmation, then is saved with the negative residual', function () {
    $tier = tierOf('PREMIUM');
    app(SetClubCapacities::class)->handle($this->season, $this->club, [new CapacityInput(InventoryResource::Partnership, $tier->id, true, 3)], false, $this->admin);
    InventoryCommitment::factory()->forTier($tier, 2)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $form = capacityForm($this->season, ['tiers' => [$tier->id => ['enabled' => true, 'quantity' => 1]]]);

    $this->actingAs($this->admin)
        ->put(route('inventory.capacities.update', $this->club), $form)
        ->assertSessionHasErrors(['confirm' => "Queste capacità restano sotto l'impegnato: Premium (impegnato 2, capacità 1). Il salvataggio è consentito e il residuo negativo resta evidenziato"]);
    expect(PartnershipCapacity::query()->sole()->quantity)->toBe(3);

    $this->actingAs($this->admin)
        ->put(route('inventory.capacities.update', $this->club), [...$form, 'confirmed' => true])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', "Capacità di SS Lazio salvate: Premium (impegnato 2, capacità 1) restano sotto l'impegnato");

    $item = collect(app(InventoryAvailability::class)->forClub($this->season, $this->club)['partnership'])->firstWhere('code', 'PREMIUM');
    expect(PartnershipCapacity::query()->sole()->quantity)->toBe(1)
        ->and($item->remaining())->toBe(-1)
        ->and($item->isBelowCommitted())->toBeTrue();
});

it('RB-10: switching off a resource with commitments also asks for confirmation', function () {
    $variant = variantOf('PREMIUM');
    app(SetClubCapacities::class)->handle($this->season, $this->club, [new CapacityInput(InventoryResource::Hospitality, $variant->id, true, 10)], false, $this->admin);
    InventoryCommitment::factory()->forVariant($variant, 4)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    expect(fn () => app(SetClubCapacities::class)->handle($this->season, $this->club, [new CapacityInput(InventoryResource::Hospitality, $variant->id, false, 10)], false, $this->admin))
        ->toThrow(CapacityBelowCommitted::class)
        ->and(HospitalityCapacity::query()->sole()->is_enabled)->toBeTrue();
});

it('D-317: a closed season is read only', function () {
    $season = Season::factory()->closed()->create();
    $club = inventoryClub($season, 'Beta');

    expect(fn () => app(SetClubCapacities::class)->handle($season, $club, [new CapacityInput(InventoryResource::Pool, poolOf('FILA_1')->id, true, 60)], false, $this->admin))
        ->toThrow(InventoryActionNotAllowed::class, "La stagione {$season->code} è chiusa: il magazzino è in sola lettura");
});

it('D-337: only Infront clubs enrolled in the season have capacities', function () {
    $other = Club::factory()->infront()->create(['name' => 'Gamma']);

    $this->actingAs($this->admin)
        ->from(route('pages.inventory_capacity'))
        ->put(route('inventory.capacities.update', $other), capacityForm($this->season, ['pools' => [poolOf('FILA_1')->id => ['enabled' => true, 'minutes' => '1']]]))
        ->assertRedirect(route('pages.inventory_capacity'))
        ->assertSessionHas('toast.message', "Gamma non è un club Infront attivo iscritto a una competizione della stagione {$this->season->code}");

    expect(PoolCapacity::query()->count())->toBe(0);
});

it('D-293: with the method "per match" the inventory refuses explicitly', function () {
    Setting::query()->create(['key' => 'availability_method', 'value' => 'per_match']);

    expect(fn () => app(InventoryAvailability::class)->forClub($this->season, $this->club))
        ->toThrow(InventoryActionNotAllowed::class)
        ->and(fn () => app(SetClubCapacities::class)->handle($this->season, $this->club, [], false, $this->admin))
        ->toThrow(InventoryActionNotAllowed::class);
});

it('FL-01: the page lists the clubs of the season with their capacities by resource', function () {
    $pool = poolOf('FILA_1');
    app(SetClubCapacities::class)->handle($this->season, $this->club, [
        new CapacityInput(InventoryResource::Pool, $pool->id, true, 1710),
        new CapacityInput(InventoryResource::Pool, poolOf('FILA_2')->id, false, 0),
    ], false, $this->admin);
    inventoryClub($this->season, 'Alfa');

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $this->season->id])
        ->get(route('pages.inventory_capacity'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Capacities/Index')
            ->where('page.season.code', $this->season->code)
            ->where('page.groups.0.resource', 'pool')
            ->where('page.groups.0.columns.0.name', '1ª fila')
            ->where('page.groups.1.columns', fn ($columns) => collect($columns)->pluck('name')->all() === ['Top', 'Premium', 'Official', 'Official 2.0'])
            ->where('page.rows.0.name', 'Alfa')
            ->where('page.rows.1.name', 'SS Lazio')
            ->where("page.rows.1.cells.pool.{$pool->id}", ['state' => 'managed', 'display' => '28′30″', 'isBelowCommitted' => false])
            ->where('page.rows.1.cells.pool.'.poolOf('FILA_2')->id.'.state', 'not_managed')
            ->where("page.rows.0.cells.pool.{$pool->id}.state", 'not_configured'));
});

it('M04 F-01: the form of a club shows capacity, committed and residual from the server', function () {
    $pool = poolOf('FILA_1');
    app(SetClubCapacities::class)->handle($this->season, $this->club, [new CapacityInput(InventoryResource::Pool, $pool->id, true, 600)], false, $this->admin);
    InventoryCommitment::factory()->forPool($pool, 630)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $this->actingAs($this->admin)
        ->get(route('inventory.capacities.edit', [$this->club, 'stagione' => $this->season->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Capacities/Edit')
            ->where('club.name', 'SS Lazio')
            ->where('step', ['seconds' => 30, 'label' => '0′30″', 'minutes' => '0,5'])
            ->where('groups.0.rows.0', [
                'valueId' => $pool->id,
                'name' => '1ª fila',
                'isEnabled' => true,
                'quantity' => '10',
                'capacity' => '10′00″',
                'committed' => '10′30″',
                'remaining' => '−0′30″',
                'isBelowCommitted' => true,
            ]));
});
