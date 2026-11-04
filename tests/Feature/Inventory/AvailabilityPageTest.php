<?php

use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Page "Magazzino › Disponibilità" and command inventory:availability (M04 F-06, F-09, RB-01, RB-08,
| RB-10; mockup "Magazzino"; D-137, D-337; F2-05). Permissions of the route are in the route × role
| matrix (RouteMatrixTest): admin and manager, never the agent.
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->season = Season::factory()->open()->create();
    $this->edition = CompetitionSeason::factory()->create([
        'season_id' => $this->season->id,
        'competition_id' => Competition::factory()->create(['name' => 'Serie A'])->id,
    ]);
    $this->club = availabilityClub($this->edition, 'SS Lazio', ['code' => 'LAZIO']);
    $this->row1 = InventoryPool::query()->where('code', 'FILA_1')->firstOrFail();
    $this->row2 = InventoryPool::query()->where('code', 'FILA_2')->firstOrFail();
});

function availabilityClub(CompetitionSeason $edition, string $name, array $state = []): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, ...$state]);
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);

    return $club;
}

function availabilityCapacity(Season $season, Club $club, InventoryPool $pool, int $seconds, bool $enabled = true): void
{
    PoolCapacity::query()->create(['season_id' => $season->id, 'club_id' => $club->id, 'inventory_pool_id' => $pool->id, 'is_enabled' => $enabled, 'seconds_per_match' => $seconds]);
}

it('M04 F-06, M04-CA1: the page shows capacity, committed and residual of a pool for every club of the season', function () {
    availabilityCapacity($this->season, $this->club, $this->row1, 1800);
    InventoryCommitment::factory()->forPool($this->row1, 90)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $this->season->id])
        ->get(route('pages.inventory_availability'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Availability/Index')
            ->where('page.season.code', $this->season->code)
            ->where('page.pools', fn ($pools) => collect($pools)->map(fn ($pool): array => [$pool['name'], $pool['isActive']])->all()
                === [['1ª fila', true], ['2ª fila', true], ['Corner bassi', false], ['Sotto tribuna', false]])
            ->where('page.competitions.0.name', 'Serie A')
            ->where('page.rows.0.name', 'SS Lazio')
            ->where("page.rows.0.pools.{$this->row1->id}", [
                'state' => 'managed',
                'capacity' => '30′00″',
                'committed' => '1′30″',
                'pending' => null,
                'remaining' => '28′30″',
                'share' => 95,
                'isSoldOut' => false,
                'isBelowCommitted' => false,
                'notSellable' => 'Format Jumbo, Domination, 1ª fila non vendibili',
            ])
            ->where("page.rows.0.pools.{$this->row2->id}.state", 'not_configured')
            ->where('page.rows.0.editUrl', route('inventory.capacities.edit', [$this->club, 'stagione' => $this->season->id])));
});

it('M04 RB-08, RB-10: sold out, not managed and negative residual are told apart', function () {
    $genoa = availabilityClub($this->edition, 'Genoa CFC');
    $udinese = availabilityClub($this->edition, 'Udinese Calcio');
    availabilityCapacity($this->season, $this->club, $this->row1, 600, enabled: false);
    availabilityCapacity($this->season, $genoa, $this->row1, 180);
    InventoryCommitment::factory()->forPool($this->row1, 180)->create(['season_id' => $this->season->id, 'club_id' => $genoa->id]);
    availabilityCapacity($this->season, $udinese, $this->row1, 510);
    InventoryCommitment::factory()->forPool($this->row1, 540)->forced()->create(['season_id' => $this->season->id, 'club_id' => $udinese->id]);

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $this->season->id])
        ->get(route('pages.inventory_availability'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.rows.0.name', 'Genoa CFC')
            ->where("page.rows.0.pools.{$this->row1->id}.isSoldOut", true)
            ->where("page.rows.0.pools.{$this->row1->id}.remaining", '0′00″')
            ->where('page.rows.1.name', 'SS Lazio')
            ->where("page.rows.1.pools.{$this->row1->id}.state", 'not_managed')
            ->where("page.rows.1.pools.{$this->row1->id}.capacity", null)
            ->where('page.rows.2.name', 'Udinese Calcio')
            ->where("page.rows.2.pools.{$this->row1->id}.remaining", '−0′30″')
            ->where("page.rows.2.pools.{$this->row1->id}.share", 0)
            ->where("page.rows.2.pools.{$this->row1->id}.isBelowCommitted", true));
});

it('M04 F-09: the side panel shows the counters of the club and what the configurator sells', function () {
    $priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $this->club->id, 'led_format_id' => LedFormat::query()->where('code', 'FILA_1')->value('id'), 'unit_price' => '3157.89']);
    availabilityCapacity($this->season, $this->club, $this->row1, 1800);
    $premium = PartnershipTier::query()->where('code', 'PREMIUM')->firstOrFail();
    PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'partnership_tier_id' => $premium->id, 'is_enabled' => true, 'quantity' => 2]);
    InventoryCommitment::factory()->forTier($premium)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $this->season->id])
        ->get(route('pages.inventory_availability'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.rows.0.tiers', fn ($tiers) => collect($tiers)->pluck('name')->all() === ['Top', 'Premium', 'Official', 'Official 2.0'])
            ->where('page.rows.0.tiers.1.capacity', '2')
            ->where('page.rows.0.tiers.1.remaining', '1')
            ->where('page.rows.0.tiers.0.state', 'not_configured')
            ->where('page.rows.0.variants.0.name', 'Premium')
            ->where('page.rows.0.sellability.selectable', true)
            ->where('page.rows.0.sellability.reason', null)
            ->where('page.rows.0.sellability.formats', [
                ['name' => 'Jumbo', 'status' => 'not_available', 'label' => 'Non disponibile'],
                ['name' => 'Domination', 'status' => 'not_available', 'label' => 'Non disponibile'],
                ['name' => '1ª fila', 'status' => 'available', 'label' => 'Vendibile · 30′00″'],
                ['name' => '2ª fila', 'status' => 'not_available', 'label' => 'Non disponibile'],
            ]));
});

it('D-137: the manager sees the availability without the link to the capacities; the reason when the club is not selectable', function () {
    PriceList::factory()->create(['season_id' => $this->season->id]);

    $this->actingAs(User::factory()->manager()->create())
        ->withSession(['sports.season_id' => $this->season->id])
        ->get(route('pages.inventory_availability'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.rows.0.editUrl', null)
            ->where('page.rows.0.sellability.selectable', false)
            ->where('page.rows.0.sellability.reason', 'il listino della stagione è in bozza')
            ->where('page.rows.0.sellability.formats', []));
});

it('M04 F-06: a season without enrolled clubs gives no rows', function () {
    $season = Season::factory()->open()->create();

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $season->id])
        ->get(route('pages.inventory_availability'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('page.season.code', $season->code)->where('page.rows', []));
});

/* Command inventory:availability (D-337 point 7). */

it('D-337: the command shows availability and sellable options of a club, read only', function () {
    $priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $this->club->id, 'led_format_id' => LedFormat::query()->where('code', 'FILA_1')->value('id'), 'unit_price' => '3157.89']);
    availabilityCapacity($this->season, $this->club, $this->row1, 1800);
    InventoryCommitment::factory()->forPool($this->row1, 90)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    expect(Artisan::call('inventory:availability', ['season' => $this->season->code, 'club' => 'lazio']))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain("SS Lazio, stagione {$this->season->code}: selezionabile nel configuratore")
        ->toContain('28′30″')
        ->toContain('3.157,89 €')
        ->toContain('Vendibile')
        ->toContain('pacchetto senza prezzo')
        ->toContain('Non disponibile');
});

it('D-337: the command gives the reason of a club not selectable and refuses unknown codes', function () {
    expect(Artisan::call('inventory:availability', ['season' => $this->season->code, 'club' => 'LAZIO']))->toBe(0)
        ->and(Artisan::output())->toContain('non selezionabile nel configuratore (la stagione non ha un listino)');

    $this->artisan('inventory:availability', ['season' => '1999-00', 'club' => 'LAZIO'])
        ->expectsOutputToContain('Stagione 1999-00 inesistente')
        ->assertFailed();
    $this->artisan('inventory:availability', ['season' => $this->season->code, 'club' => 'NESSUNO'])
        ->expectsOutputToContain('Club NESSUNO inesistente')
        ->assertFailed();
});
