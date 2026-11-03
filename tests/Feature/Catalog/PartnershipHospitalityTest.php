<?php

use App\Domain\Catalog\Actions\SetPrice;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\AuditAction;
use App\Enums\AuditArea;
use App\Enums\PriceKind;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\HospitalityPrice;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Partnership and hospitality prices of a price list (M03 F-07, F-09, RB-03…RB-05, §8; D-029, D-055,
| D-333, D-341; F2-03). Permissions of every route are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function partnershipPriceListClub(PriceList $priceList, string $name, array $state = []): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, ...$state]);
    ClubEnrollment::query()->create([
        'competition_season_id' => CompetitionSeason::factory()->create(['season_id' => $priceList->season_id])->id,
        'club_id' => $club->id,
    ]);

    return $club;
}

function partnershipTierOf(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function hospitalityVariantOf(string $code): HospitalityVariant
{
    return HospitalityVariant::query()->where('code', $code)->firstOrFail();
}

it('M03 F-07, D-029: the admin writes the seasonal price of a tier, with an audit event', function () {
    $priceList = PriceList::factory()->create();
    $lazio = partnershipPriceListClub($priceList, 'SS Lazio', ['code' => 'LAZIO']);
    $premium = partnershipTierOf('PREMIUM');

    $this->actingAs($this->admin)
        ->put(route('catalog.prices.update', [$priceList, 'partnership']), ['club_id' => $lazio->id, 'column_id' => $premium->id, 'amount' => '135.000'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Prezzo di SS Lazio, Premium salvato');

    expect(PartnershipPrice::query()->sole()->season_price)->toBe('135000.00');

    $event = soleAuditEvent(AuditAction::PartnershipPriceUpdated);
    expect($event->area)->toBe(AuditArea::Catalog->value)
        ->and($event->object_label)->toBe("Listino {$priceList->season->code} · SS Lazio (LAZIO) · Premium (PREMIUM)")
        ->and($event->old_values)->toBe(['season_price' => null])
        ->and($event->new_values)->toBe(['season_price' => '135000.00']);
});

it('RB-04: the free package "Libera" has no price and is not a column of the grid', function () {
    $priceList = PriceList::factory()->create();
    $club = partnershipPriceListClub($priceList, 'Alfa');

    expect(fn () => app(SetPrice::class)->handle($priceList, PriceKind::Partnership, $club, partnershipTierOf('LIBERA'), '1000', $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, 'Il pacchetto Libera non ha prezzo né benefit')
        ->and(app(PriceListGrid::class)->columns(PriceKind::Partnership)->pluck('code')->all())->toBe(['TOP', 'PREMIUM', 'OFFICIAL', 'OFFICIAL_2'])
        ->and(PartnershipPrice::query()->count())->toBe(0);
});

it('M03 F-09, D-055: the admin writes the price per seat of a variant, with an audit event', function () {
    $priceList = PriceList::factory()->published()->create();
    $club = partnershipPriceListClub($priceList, 'Beta');
    $variant = hospitalityVariantOf('PREMIUM');

    $this->actingAs($this->admin)
        ->put(route('catalog.prices.update', [$priceList, 'hospitality']), ['club_id' => $club->id, 'column_id' => $variant->id, 'amount' => '4.500,00'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Prezzo di Beta, Premium salvato');

    expect(HospitalityPrice::query()->sole()->seat_price)->toBe('4500.00')
        ->and(soleAuditEvent(AuditAction::HospitalityPriceUpdated)->new_values)->toBe(['seat_price' => '4500.00']);

    $this->put(route('catalog.prices.update', [$priceList, 'hospitality']), ['club_id' => $club->id, 'column_id' => $variant->id, 'amount' => ''])
        ->assertSessionHas('toast.message', 'Beta, Premium: ora non disponibile');

    expect(HospitalityPrice::query()->count())->toBe(0);
});

it('D-331: a deactivated tier or variant gets no new prices', function (string $kind, string $code, string $message) {
    $priceList = PriceList::factory()->create();
    $club = partnershipPriceListClub($priceList, 'Gamma');
    $priceKind = PriceKind::from($kind);
    $value = $priceKind->columnModel()::query()->where('code', $code)->firstOrFail();
    $value->update(['is_active' => false]);

    expect(fn () => app(SetPrice::class)->handle($priceList, $priceKind, $club, $value, '10', $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, $message);
})->with([
    'tier' => ['partnership', 'OFFICIAL_2', 'Il livello Official 2.0 non è attivo'],
    'variant' => ['hospitality', 'BASE', 'La variante Base non è attiva'],
]);

it('answers 404 to an unknown kind of price', function () {
    $priceList = PriceList::factory()->create();

    $this->actingAs($this->admin)
        ->put('/catalogo/listini/'.$priceList->id.'/prezzi/virtual', ['club_id' => 1, 'column_id' => 1, 'amount' => '1'])
        ->assertNotFound();
});

it('M03 §8, D-333: a partnership price is an offer, hospitality alone is not', function () {
    $priceList = PriceList::factory()->create();
    $withTier = partnershipPriceListClub($priceList, 'Con livello');
    $onlySeats = partnershipPriceListClub($priceList, 'Solo hospitality');
    $setPrice = app(SetPrice::class);
    $setPrice->handle($priceList, PriceKind::Partnership, $withTier, partnershipTierOf('TOP'), '200000', $this->admin);
    $setPrice->handle($priceList, PriceKind::Hospitality, $onlySeats, hospitalityVariantOf('BASE'), '1500', $this->admin);

    expect(app(PriceListGrid::class)->clubsWithoutOffers($priceList)->pluck('name')->all())->toBe(['Solo hospitality']);
});

it('D-335: the partnership page shows the tiers, the link to the benefits of every club and the general on-top texts', function () {
    $season = Season::factory()->open()->create();
    $priceList = PriceList::factory()->create(['season_id' => $season->id]);
    $club = partnershipPriceListClub($priceList, 'Delta');
    app(SetPrice::class)->handle($priceList, PriceKind::Partnership, $club, partnershipTierOf('TOP'), '180000', $this->admin);
    $top = (string) partnershipTierOf('TOP')->id;

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $season->id])
        ->get(PortalPages::url('catalog_partnership'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/PriceList/Index')
            ->where('layout.title', 'Partnership')
            ->where('page.kind', 'partnership')
            ->has('page.columns', 4)
            ->where('page.priceList.updateUrl', route('catalog.prices.update', [$priceList, 'partnership']))
            ->where("page.rows.0.prices.{$top}", ['value' => '180000.00', 'display' => '180.000,00'])
            ->where('page.rows.0.benefitsUrl', route('catalog.benefits.show', [$priceList, $club]))
            ->where('page.onTop.items', [])
            ->where('page.onTop.clubId', null)
            ->where('page.onTop.createUrl', route('catalog.on-top.store', $priceList)));
});

it('D-137: the manager reads the hospitality page without editing it', function () {
    $season = Season::factory()->open()->create();
    $priceList = PriceList::factory()->create(['season_id' => $season->id]);
    partnershipPriceListClub($priceList, 'Epsilon');

    $this->actingAs(User::factory()->manager()->create())
        ->withSession(['sports.season_id' => $season->id])
        ->get(PortalPages::url('catalog_hospitality'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.kind', 'hospitality')
            ->where('page.canManage', false)
            ->where('page.columns.0.code', 'PREMIUM')
            ->where('page.columns.1.code', 'BASE')
            ->where('page.rows.0.benefitsUrl', null)
            ->where('page.onTop', null));
});

it('D-341: the LED grid keeps working with the generic prices', function () {
    $priceList = PriceList::factory()->create();
    $club = partnershipPriceListClub($priceList, 'Zeta');
    $format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();

    app(SetPrice::class)->handle($priceList, PriceKind::Led, $club, $format, '842.11', $this->admin);

    expect($priceList->ledPrices()->sole()->unit_price)->toBe('842.11');
});
