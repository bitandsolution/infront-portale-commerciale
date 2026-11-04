<?php

use App\Domain\Inventory\Data\ClubSellability;
use App\Domain\Inventory\Exceptions\InventoryActionNotAllowed;
use App\Domain\Inventory\Services\SellableOptions;
use App\Enums\ClubSellabilityReason;
use App\Enums\PriceKind;
use App\Enums\SellableStatus;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\HospitalityCapacity;
use App\Models\HospitalityPrice;
use App\Models\HospitalityVariant;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\Setting;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Facades\DB;

/*
| Sellable options of a club for the configurator (M03 RB-09, M04 F-09, RB-08; M03-CA4, M04-CA3,
| M04-CA4; D-051, D-052, D-066, D-085, D-190, D-337, D-347; F2-05). The interface of the
| configurator arrives with F4 (D-337 point 3): the criteria are verified on the service.
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->season = Season::factory()->open()->create();
    $this->priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $this->club = sellableClub($this->season, 'SS Lazio', ['code' => 'LAZIO']);
});

function sellableClub(Season $season, string $name, array $state = []): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, ...$state]);
    ClubEnrollment::query()->create([
        'competition_season_id' => CompetitionSeason::factory()->create(['season_id' => $season->id])->id,
        'club_id' => $club->id,
    ]);

    return $club;
}

function sellableFormat(string $code): LedFormat
{
    return LedFormat::query()->where('code', $code)->firstOrFail();
}

function sellablePool(string $code): InventoryPool
{
    return InventoryPool::query()->where('code', $code)->firstOrFail();
}

function sellableTier(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function sellableVariant(string $code): HospitalityVariant
{
    return HospitalityVariant::query()->where('code', $code)->firstOrFail();
}

/** LED prices (cost for 1′ per match) of the club for the given formats. */
function sellableLedPrices(PriceList $priceList, Club $club, array $codes, string $price = '3157.89'): void
{
    foreach ($codes as $code) {
        LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $club->id, 'led_format_id' => sellableFormat($code)->id, 'unit_price' => $price]);
    }
}

function sellablePoolCapacity(Season $season, Club $club, string $pool, int $seconds, bool $enabled = true): void
{
    PoolCapacity::query()->create(['season_id' => $season->id, 'club_id' => $club->id, 'inventory_pool_id' => sellablePool($pool)->id, 'is_enabled' => $enabled, 'seconds_per_match' => $seconds]);
}

function sellableResult(Season $season, Club $club): ClubSellability
{
    return app(SellableOptions::class)->forClub($season, $club);
}

/** Codes of the options shown by the configurator (available or sold out). */
function sellableVisibleCodes(array $options): array
{
    return array_map(fn ($option): string => $option->code, ClubSellability::visible($options));
}

/* Formats with and without a price (M03-CA4, D-190). */

it('M03-CA4: a format marked "not available" for a club (no price) is not shown by the configurator for that club', function () {
    sellableLedPrices($this->priceList, $this->club, ['FILA_1', 'FILA_2']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_1', 1800);
    sellablePoolCapacity($this->season, $this->club, 'FILA_2', 1800);

    $result = sellableResult($this->season, $this->club);

    expect($result->isSelectable())->toBeTrue()
        ->and($result->option(PriceKind::Led, 'JUMBO')?->status)->toBe(SellableStatus::NotAvailable)
        ->and($result->option(PriceKind::Led, 'DOMINATION')?->status)->toBe(SellableStatus::NotAvailable)
        ->and(sellableVisibleCodes($result->led))->toBe(['FILA_1', 'FILA_2']);

    $other = sellableClub($this->season, 'Genoa CFC');
    sellableLedPrices($this->priceList, $other, ['JUMBO']);
    sellablePoolCapacity($this->season, $other, 'FILA_1', 1800);
    sellablePoolCapacity($this->season, $other, 'FILA_2', 1800);

    expect(sellableVisibleCodes(sellableResult($this->season, $other)->led))->toBe(['JUMBO']);
});

/* Pools not managed (M04-CA3, D-066, D-347). */

it('M04-CA3: for Lazio with the 1st row switched off the configurator does not show the formats 1st row, Jumbo and Domination', function () {
    sellableLedPrices($this->priceList, $this->club, ['JUMBO', 'DOMINATION', 'FILA_1', 'FILA_2']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_1', 1800, enabled: false);
    sellablePoolCapacity($this->season, $this->club, 'FILA_2', 1800);

    $result = sellableResult($this->season, $this->club);

    foreach (['JUMBO', 'DOMINATION', 'FILA_1'] as $code) {
        expect($result->option(PriceKind::Led, $code)?->status)->toBe(SellableStatus::NotManaged)
            ->and($result->option(PriceKind::Led, $code)?->remaining)->toBeNull();
    }

    expect(sellableVisibleCodes($result->led))->toBe(['FILA_2'])
        ->and($result->option(PriceKind::Led, 'FILA_2')?->remaining)->toBe(1800);
});

it('D-347: a pool whose capacity was never set is not managed, so its formats are not sellable', function () {
    sellableLedPrices($this->priceList, $this->club, ['JUMBO', 'FILA_2']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_2', 600);

    $result = sellableResult($this->season, $this->club);

    expect($result->option(PriceKind::Led, 'JUMBO')?->status)->toBe(SellableStatus::NotManaged)
        ->and($result->option(PriceKind::Led, 'FILA_2')?->status)->toBe(SellableStatus::Available);
});

it('M04 RB-08: a pool deactivated in the catalogue makes the formats that consume it not managed', function () {
    sellableLedPrices($this->priceList, $this->club, ['JUMBO', 'FILA_1']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_1', 1800);
    sellablePoolCapacity($this->season, $this->club, 'FILA_2', 1800);
    sellablePool('FILA_2')->update(['is_active' => false]);

    $result = sellableResult($this->season, $this->club);

    expect($result->option(PriceKind::Led, 'JUMBO')?->status)->toBe(SellableStatus::NotManaged)
        ->and($result->option(PriceKind::Led, 'FILA_1')?->status)->toBe(SellableStatus::Available);
});

/* Sold out (M04-CA4, M04 §10). */

it('M04-CA4: with the capacity managed and 0 the format is visible as sold out and not selectable', function () {
    sellableLedPrices($this->priceList, $this->club, ['DOMINATION', 'FILA_1', 'FILA_2']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_1', 0);
    sellablePoolCapacity($this->season, $this->club, 'FILA_2', 1800);

    $result = sellableResult($this->season, $this->club);
    $row1 = $result->option(PriceKind::Led, 'FILA_1');

    expect($row1?->status)->toBe(SellableStatus::SoldOut)
        ->and($row1?->status->isVisible())->toBeTrue()
        ->and($row1?->status->isSelectable())->toBeFalse()
        ->and($row1?->remaining)->toBe(0)
        ->and($result->option(PriceKind::Led, 'DOMINATION')?->status)->toBe(SellableStatus::SoldOut)
        ->and(sellableVisibleCodes($result->led))->toBe(['DOMINATION', 'FILA_1', 'FILA_2']);
});

it('M04 §10: after a forced approval the residual is negative, the format is sold out and the agent sees 0', function () {
    sellableLedPrices($this->priceList, $this->club, ['FILA_1']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_1', 60);
    InventoryCommitment::factory()->forPool(sellablePool('FILA_1'), 90)->forced()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $option = sellableResult($this->season, $this->club)->option(PriceKind::Led, 'FILA_1');

    expect($option?->status)->toBe(SellableStatus::SoldOut)
        ->and($option?->remaining)->toBe(0);
});

/* Formats on two pools (D-051) and residuals (M04 RB-01, RB-12). */

it('D-051: the residual of Jumbo is the smallest residual of the 1st and 2nd row', function () {
    sellableLedPrices($this->priceList, $this->club, ['JUMBO', 'FILA_1', 'FILA_2']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_1', 1800);
    sellablePoolCapacity($this->season, $this->club, 'FILA_2', 450);
    InventoryCommitment::factory()->forPool(sellablePool('FILA_2'), 60)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);
    InventoryCommitment::factory()->forPool(sellablePool('FILA_1'), 90)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $result = sellableResult($this->season, $this->club);

    expect($result->option(PriceKind::Led, 'JUMBO')?->remaining)->toBe(390)
        ->and($result->option(PriceKind::Led, 'FILA_1')?->remaining)->toBe(1710)
        ->and($result->option(PriceKind::Led, 'FILA_2')?->remaining)->toBe(390)
        ->and($result->option(PriceKind::Led, 'FILA_1')?->listPrice)->toBe('3157.89');
});

it('M04 RB-01: released commitments and commitments of other clubs or seasons do not count', function () {
    sellableLedPrices($this->priceList, $this->club, ['FILA_1']);
    sellablePoolCapacity($this->season, $this->club, 'FILA_1', 600);
    $pool = sellablePool('FILA_1');
    InventoryCommitment::factory()->forPool($pool, 120)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);
    $released = InventoryCommitment::factory()->forPool($pool, 300)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);
    DB::table('inventory_commitments')->where('id', $released->id)->update(['released_at' => now(), 'released_by' => $released->created_by, 'release_reason' => 'cancelled']);
    InventoryCommitment::factory()->forPool($pool, 300)->create(['season_id' => $this->season->id, 'club_id' => sellableClub($this->season, 'Genoa CFC')->id]);
    InventoryCommitment::factory()->forPool($pool, 300)->create(['season_id' => Season::factory()->create()->id, 'club_id' => $this->club->id]);

    expect(sellableResult($this->season, $this->club)->option(PriceKind::Led, 'FILA_1')?->remaining)->toBe(480);
});

/* Partnership (D-052, D-071) and hospitality (D-055). */

it('D-052, D-071: tiers with their counters; the free package is always available without price or counter', function () {
    foreach (['TOP' => '200000.00', 'PREMIUM' => '135000.00', 'OFFICIAL' => '80000.00'] as $code => $price) {
        PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->club->id, 'partnership_tier_id' => sellableTier($code)->id, 'season_price' => $price]);
    }

    PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'partnership_tier_id' => sellableTier('TOP')->id, 'is_enabled' => true, 'quantity' => 1]);
    PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'partnership_tier_id' => sellableTier('PREMIUM')->id, 'is_enabled' => true, 'quantity' => 3]);
    PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'partnership_tier_id' => sellableTier('OFFICIAL')->id, 'is_enabled' => false, 'quantity' => 5]);
    InventoryCommitment::factory()->forTier(sellableTier('TOP'))->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);
    InventoryCommitment::factory()->forTier(sellableTier('PREMIUM'))->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $result = sellableResult($this->season, $this->club);
    $free = $result->option(PriceKind::Partnership, 'LIBERA');

    expect($free?->status)->toBe(SellableStatus::Available)
        ->and($free?->isFreePackage)->toBeTrue()
        ->and($free?->listPrice)->toBeNull()
        ->and($free?->remaining)->toBeNull()
        ->and($result->option(PriceKind::Partnership, 'TOP')?->status)->toBe(SellableStatus::SoldOut)
        ->and($result->option(PriceKind::Partnership, 'PREMIUM')?->status)->toBe(SellableStatus::Available)
        ->and($result->option(PriceKind::Partnership, 'PREMIUM')?->remaining)->toBe(2)
        ->and($result->option(PriceKind::Partnership, 'PREMIUM')?->listPrice)->toBe('135000.00')
        ->and($result->option(PriceKind::Partnership, 'OFFICIAL')?->status)->toBe(SellableStatus::NotManaged)
        ->and($result->option(PriceKind::Partnership, 'OFFICIAL_2')?->status)->toBe(SellableStatus::NotAvailable)
        ->and(sellableVisibleCodes($result->partnership))->toBe(['LIBERA', 'TOP', 'PREMIUM']);
});

it('D-055, D-053: hospitality variants with a price per seat and the residual seats', function () {
    HospitalityPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->club->id, 'hospitality_variant_id' => sellableVariant('PREMIUM')->id, 'seat_price' => '2500.00']);
    HospitalityCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'hospitality_variant_id' => sellableVariant('PREMIUM')->id, 'is_enabled' => true, 'seats' => 300]);
    HospitalityCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'hospitality_variant_id' => sellableVariant('BASE')->id, 'is_enabled' => true, 'seats' => 132]);
    InventoryCommitment::factory()->forVariant(sellableVariant('PREMIUM'), 6)->create(['season_id' => $this->season->id, 'club_id' => $this->club->id]);

    $result = sellableResult($this->season, $this->club);

    expect($result->option(PriceKind::Hospitality, 'PREMIUM')?->status)->toBe(SellableStatus::Available)
        ->and($result->option(PriceKind::Hospitality, 'PREMIUM')?->remaining)->toBe(294)
        ->and($result->option(PriceKind::Hospitality, 'PREMIUM')?->listPrice)->toBe('2500.00')
        ->and($result->option(PriceKind::Hospitality, 'BASE')?->status)->toBe(SellableStatus::NotAvailable);
});

/* Selectable clubs (M06 RB-09, M03 RB-09, D-085, D-190, D-337). */

it('M03 RB-09: with the price list in draft no club is selectable', function () {
    $season = Season::factory()->open()->create();
    $club = sellableClub($season, 'Genoa CFC');
    sellableLedPrices(PriceList::factory()->create(['season_id' => $season->id]), $club, ['FILA_1']);

    $result = sellableResult($season, $club);

    expect($result->isSelectable())->toBeFalse()
        ->and($result->reason)->toBe(ClubSellabilityReason::PriceListDraft)
        ->and($result->options())->toBe([]);
});

it('D-190: without a price list, or without any price in the published one, the club is not selectable', function () {
    $season = Season::factory()->create();
    $club = sellableClub($season, 'Genoa CFC');

    expect(sellableResult($season, $club)->reason)->toBe(ClubSellabilityReason::NoPriceList)
        ->and(sellableResult($this->season, $this->club)->reason)->toBe(ClubSellabilityReason::NoPrices);

    HospitalityPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $this->club->id, 'hospitality_variant_id' => sellableVariant('BASE')->id, 'seat_price' => '900.00']);

    expect(sellableResult($this->season, $this->club)->isSelectable())->toBeTrue();
});

it('D-337, D-085: a club that is not Infront, not active or not enrolled in the season is not selectable', function () {
    $opponent = sellableClub($this->season, 'Juventus FC', ['is_infront' => false]);
    $inactive = sellableClub($this->season, 'US Salernitana', ['is_active' => false]);
    $elsewhere = sellableClub(Season::factory()->create(), 'Genoa CFC');

    foreach ([$opponent, $inactive, $elsewhere] as $club) {
        sellableLedPrices($this->priceList, $club, ['FILA_1']);

        expect(sellableResult($this->season, $club)->reason)->toBe(ClubSellabilityReason::NotInSeason);
    }
});

it('M06 RB-09: the selectable clubs of the season are the ones with a price in the published list, by name', function () {
    $genoa = sellableClub($this->season, 'Genoa CFC');
    sellableClub($this->season, 'Cagliari Calcio');
    sellableLedPrices($this->priceList, $this->club, ['FILA_1']);
    PartnershipPrice::query()->create(['price_list_id' => $this->priceList->id, 'club_id' => $genoa->id, 'partnership_tier_id' => sellableTier('TOP')->id, 'season_price' => '90000.00']);

    $clubs = array_map(fn (ClubSellability $item): string => $item->club->name, app(SellableOptions::class)->selectableClubs($this->season));

    expect($clubs)->toBe(['Genoa CFC', 'SS Lazio']);
});

it('D-293: with the method "per match" the service refuses explicitly', function () {
    Setting::query()->updateOrCreate(['key' => 'availability_method'], ['value' => 'per_match']);

    expect(fn () => sellableResult($this->season, $this->club))->toThrow(InventoryActionNotAllowed::class);
});
