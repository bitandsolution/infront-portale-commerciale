<?php

use App\Domain\Catalog\Actions\SetLedPrice;
use App\Domain\Catalog\Data\PriceChange;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\AuditArea;
use App\Enums\PriceListStatus;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Price list of a season and LED prices (M03 F-05, F-11, §8; D-190, D-333, D-336, D-337; F2-02).
| Permissions of every route are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

/**
 * An Infront club enrolled in an edition of the season (a row of the grid, D-337).
 */
function ledPriceListClub(CompetitionSeason $edition, string $name, array $state = []): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, ...$state]);
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);

    return $club;
}

function ledPriceListFormat(string $code): LedFormat
{
    return LedFormat::query()->where('code', $code)->firstOrFail();
}

function ledPriceListEdition(Season $season, string $name, int $sortOrder): CompetitionSeason
{
    return CompetitionSeason::factory()->create([
        'season_id' => $season->id,
        'competition_id' => Competition::factory()->create(['name' => $name, 'sort_order' => $sortOrder])->id,
    ]);
}

function ledPriceOf(PriceList $priceList, Club $club, LedFormat $format): ?string
{
    return LedPrice::query()
        ->where('price_list_id', $priceList->id)
        ->where('club_id', $club->id)
        ->where('led_format_id', $format->id)
        ->value('unit_price');
}

/* Creation (F-11, D-333). */

it('D-333: the admin creates the price list of the season empty in draft, with an audit event', function () {
    $season = Season::factory()->open()->create();

    $this->actingAs($this->admin)
        ->post(route('catalog.price-lists.store'), ['season_id' => $season->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', "Listino {$season->code} creato in bozza");

    $priceList = PriceList::query()->where('season_id', $season->id)->sole();
    expect($priceList->status)->toBe(PriceListStatus::Draft)
        ->and($priceList->published_at)->toBeNull()
        ->and($priceList->ledPrices()->count())->toBe(0);

    $event = soleAuditEvent(AuditAction::PriceListCreated);
    expect($event->area)->toBe(AuditArea::Catalog->value)
        ->and($event->user_id)->toBe($this->admin->id)
        ->and($event->object_type)->toBe('price_list')
        ->and($event->object_id)->toBe($priceList->id)
        ->and($event->object_label)->toBe("Listino {$season->code}")
        ->and($event->new_values)->toBe(['status' => 'draft']);
});

it('D-190: a second price list of the same season is refused', function () {
    $priceList = PriceList::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('catalog.price-lists.store'), ['season_id' => $priceList->season_id])
        ->assertSessionHas('toast.message', "Il listino della stagione {$priceList->season->code} esiste già");

    expect(PriceList::query()->count())->toBe(1)
        ->and(auditEventsOf(AuditAction::PriceListCreated))->toHaveCount(0);
});

it('D-333: no price list in a closed season', function () {
    $season = Season::factory()->closed()->create();

    $this->actingAs($this->admin)
        ->post(route('catalog.price-lists.store'), ['season_id' => $season->id])
        ->assertSessionHas('toast.message', "La stagione {$season->code} è chiusa: il suo listino è in sola lettura");

    expect(PriceList::query()->count())->toBe(0);
});

/* LED prices (F-05, §8, D-333). */

it('D-054, D-057: the admin writes the cost for 1′ per match in Italian notation, with an audit event', function () {
    $priceList = PriceList::factory()->create();
    $lazio = ledPriceListClub(CompetitionSeason::factory()->create(['season_id' => $priceList->season_id]), 'SS Lazio', ['code' => 'LAZIO']);
    $format = ledPriceListFormat('FILA_1');

    $this->actingAs($this->admin)
        ->put(route('catalog.led-prices.update', $priceList), ['club_id' => $lazio->id, 'led_format_id' => $format->id, 'unit_price' => '3.157,89'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Prezzo di SS Lazio, 1ª fila salvato');

    expect(ledPriceOf($priceList, $lazio, $format))->toBe('3157.89');

    $event = soleAuditEvent(AuditAction::LedPriceUpdated);
    expect($event->area)->toBe(AuditArea::Catalog->value)
        ->and($event->object_type)->toBe('price_list')
        ->and($event->object_id)->toBe($priceList->id)
        ->and($event->object_label)->toBe("Listino {$priceList->season->code} · SS Lazio (LAZIO) · 1ª fila (FILA_1)")
        ->and($event->old_values)->toBe(['unit_price' => null])
        ->and($event->new_values)->toBe(['unit_price' => '3157.89']);
});

it('D-333: changing a price records the value before and after; the same value writes nothing', function () {
    $priceList = PriceList::factory()->create();
    $club = ledPriceListClub(CompetitionSeason::factory()->create(['season_id' => $priceList->season_id]), 'Alfa');
    $format = ledPriceListFormat('DOMINATION');
    $action = app(SetLedPrice::class);

    expect($action->handle($priceList, $club, $format, '4736.84', $this->admin))->toBe(PriceChange::Created)
        ->and($action->handle($priceList, $club, $format, '4800', $this->admin))->toBe(PriceChange::Updated)
        ->and($action->handle($priceList, $club, $format, '4800.00', $this->admin))->toBe(PriceChange::Unchanged);

    $events = auditEventsOf(AuditAction::LedPriceUpdated);
    expect($events)->toHaveCount(2)
        ->and($events[1]->old_values)->toBe(['unit_price' => '4736.84'])
        ->and($events[1]->new_values)->toBe(['unit_price' => '4800.00'])
        ->and(ledPriceOf($priceList, $club, $format))->toBe('4800.00');
});

it('D-190: emptying the cell makes the format not available again: the row is removed, with an audit event', function () {
    $priceList = PriceList::factory()->create();
    $club = ledPriceListClub(CompetitionSeason::factory()->create(['season_id' => $priceList->season_id]), 'Beta');
    $format = ledPriceListFormat('JUMBO');
    app(SetLedPrice::class)->handle($priceList, $club, $format, '5263.16', $this->admin);

    $this->actingAs($this->admin)
        ->put(route('catalog.led-prices.update', $priceList), ['club_id' => $club->id, 'led_format_id' => $format->id, 'unit_price' => ''])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Beta, Jumbo: ora non disponibile');

    expect(ledPriceOf($priceList, $club, $format))->toBeNull()
        ->and(auditEventsOf(AuditAction::LedPriceUpdated)->last()?->new_values)->toBe(['unit_price' => null]);
});

it('M03 §8: the cost is ≥ 0 with at most 2 decimals, in Italian notation', function (string $input) {
    $priceList = PriceList::factory()->create();
    $club = ledPriceListClub(CompetitionSeason::factory()->create(['season_id' => $priceList->season_id]), 'Gamma');

    $this->actingAs($this->admin)
        ->put(route('catalog.led-prices.update', $priceList), ['club_id' => $club->id, 'led_format_id' => ledPriceListFormat('FILA_2')->id, 'unit_price' => $input])
        ->assertSessionHasErrors('unit_price');

    expect(LedPrice::query()->count())->toBe(0)
        ->and(auditEventsOf(AuditAction::LedPriceUpdated))->toHaveCount(0);
})->with([
    'negative' => ['-10,00'],
    'three decimals' => ['1.052,634'],
    'dot as decimal separator' => ['3157.89'],
    'text' => ['tremila'],
    'beyond DECIMAL(12,2)' => ['10.000.000.000,00'],
]);

it('D-337: only the active Infront clubs enrolled in the season are rows of the grid', function (array $state, bool $enrolled) {
    $priceList = PriceList::factory()->create();
    $club = Club::factory()->create(['name' => 'Fuori listino', ...$state]);

    if ($enrolled) {
        ClubEnrollment::query()->create(['competition_season_id' => CompetitionSeason::factory()->create(['season_id' => $priceList->season_id])->id, 'club_id' => $club->id]);
    }

    expect(fn () => app(SetLedPrice::class)->handle($priceList, $club, ledPriceListFormat('FILA_1'), '100', $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, 'Fuori listino non è un club Infront attivo iscritto a una competizione della stagione '.$priceList->season->code);
})->with([
    'opponent' => [['is_infront' => false], true],
    'inactive' => [['is_infront' => true, 'is_active' => false], true],
    'not enrolled in the season' => [['is_infront' => true], false],
]);

it('D-331: a deactivated format gets no new prices', function () {
    $priceList = PriceList::factory()->create();
    $club = ledPriceListClub(CompetitionSeason::factory()->create(['season_id' => $priceList->season_id]), 'Delta');
    $format = ledPriceListFormat('JUMBO');
    $format->update(['is_active' => false]);

    expect(fn () => app(SetLedPrice::class)->handle($priceList, $club, $format, '100', $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, 'Il format Jumbo non è attivo: riattivalo da Catalogo, Valori');
});

it('D-333: prices stay editable after the publication, each change with its audit event', function () {
    $priceList = PriceList::factory()->published()->create();
    $club = ledPriceListClub(CompetitionSeason::factory()->create(['season_id' => $priceList->season_id]), 'Epsilon');

    $this->actingAs($this->admin)
        ->put(route('catalog.led-prices.update', $priceList), ['club_id' => $club->id, 'led_format_id' => ledPriceListFormat('FILA_1')->id, 'unit_price' => '2.526,32'])
        ->assertSessionHasNoErrors();

    expect(ledPriceOf($priceList, $club, ledPriceListFormat('FILA_1')))->toBe('2526.32')
        ->and(auditEventsOf(AuditAction::LedPriceUpdated))->toHaveCount(1);
});

it('D-333: the price list of a closed season is read-only', function () {
    $season = Season::factory()->open()->create();
    $priceList = PriceList::factory()->create(['season_id' => $season->id]);
    $club = ledPriceListClub(CompetitionSeason::factory()->create(['season_id' => $season->id]), 'Zeta');
    $season->update(['status' => 'closed']);

    $this->actingAs($this->admin)
        ->put(route('catalog.led-prices.update', $priceList), ['club_id' => $club->id, 'led_format_id' => ledPriceListFormat('FILA_1')->id, 'unit_price' => '10'])
        ->assertSessionHas('toast.message', "La stagione {$season->code} è chiusa: il suo listino è in sola lettura");

    $this->post(route('catalog.price-lists.publish', $priceList))
        ->assertSessionHas('toast.message', "La stagione {$season->code} è chiusa: il suo listino è in sola lettura");

    expect(LedPrice::query()->count())->toBe(0)
        ->and($priceList->refresh()->isPublished())->toBeFalse();
});

/* Publication (F-11, FL-01 step 3, §8; D-333). */

it('D-333: publishing is final, with date, author and the number of clubs without offers in the audit', function () {
    $priceList = PriceList::factory()->create();
    $edition = CompetitionSeason::factory()->create(['season_id' => $priceList->season_id]);
    $offered = ledPriceListClub($edition, 'Alfa');
    ledPriceListClub($edition, 'Beta');
    app(SetLedPrice::class)->handle($priceList, $offered, ledPriceListFormat('FILA_1'), '1000', $this->admin);

    $this->actingAs($this->admin)
        ->post(route('catalog.price-lists.publish', $priceList))
        ->assertSessionHas('toast.message', "Listino {$priceList->season->code} pubblicato: da ora è usato dal configuratore");

    $priceList->refresh();
    expect($priceList->status)->toBe(PriceListStatus::Published)
        ->and($priceList->published_by)->toBe($this->admin->id)
        ->and($priceList->published_at)->not->toBeNull();

    $event = soleAuditEvent(AuditAction::PriceListPublished);
    expect($event->old_values)->toBe(['status' => 'draft'])
        ->and($event->new_values)->toBe(['status' => 'published', 'clubs_without_offers' => 1]);

    $this->post(route('catalog.price-lists.publish', $priceList))
        ->assertSessionHas('toast.message', "Il listino {$priceList->season->code} è già pubblicato: la pubblicazione non si annulla");

    expect(auditEventsOf(AuditAction::PriceListPublished))->toHaveCount(1);
});

/* Page (mockup "Listino LED", D-337). */

it('D-337: the admin sees the grid club × format of the season, with the competitions of the filter and the clubs without offers', function () {
    $season = Season::factory()->open()->create();
    $priceList = PriceList::factory()->create(['season_id' => $season->id]);
    $serieA = ledPriceListEdition($season, 'Serie A', 1);
    $serieB = ledPriceListEdition($season, 'Serie B', 2);
    $lazio = ledPriceListClub($serieA, 'SS Lazio');
    $bari = ledPriceListClub($serieB, 'SSC Bari');
    Club::factory()->create(['name' => 'Avversario']);
    app(SetLedPrice::class)->handle($priceList, $lazio, ledPriceListFormat('FILA_1'), '3157.89', $this->admin);
    $fila1 = (string) ledPriceListFormat('FILA_1')->id;

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $season->id])
        ->get(PortalPages::url('catalog_led'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Led/Index')
            ->where('page.season.code', $season->code)
            ->where('page.canManage', true)
            ->where('page.priceList.status', 'draft')
            ->where('page.priceList.statusLabel', 'Bozza')
            ->where('page.priceList.updateUrl', route('catalog.led-prices.update', $priceList))
            ->where('page.competitions', [
                ['id' => $serieA->competition_id, 'name' => 'Serie A'],
                ['id' => $serieB->competition_id, 'name' => 'Serie B'],
            ])
            ->has('page.formats', 4)
            ->where('page.formats.0.code', 'JUMBO')
            ->has('page.rows', 2)
            ->where('page.rows.0.name', 'SS Lazio')
            ->where('page.rows.0.competitionIds', [$serieA->competition_id])
            ->where("page.rows.0.prices.{$fila1}", ['value' => '3157.89', 'display' => '3.157,89'])
            ->where('page.rows.1.clubId', $bari->id)
            ->where("page.rows.1.prices.{$fila1}", null)
            ->where('page.withoutOffers', ['SSC Bari']));
});

it('D-137: the manager reads the price list without editing it', function () {
    $season = Season::factory()->open()->create();
    PriceList::factory()->published()->create(['season_id' => $season->id]);

    $this->actingAs(User::factory()->manager()->create())
        ->withSession(['sports.season_id' => $season->id])
        ->get(PortalPages::url('catalog_led'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.canManage', false)
            ->where('page.priceList.isPublished', true)
            ->where('page.withoutOffers', []));
});

it('shows the empty state without any season', function () {
    $this->actingAs($this->admin)
        ->get(PortalPages::url('catalog_led'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('page', null));
});

/*
 * A separate test: CurrentSeason is resolved once per request (scoped binding), and two requests of
 * the same test share it.
 */
it('shows the empty state of a season without a price list, with the creation for the admin', function () {
    $season = Season::factory()->open()->create();

    $this->actingAs($this->admin)
        ->withSession(['sports.season_id' => $season->id])
        ->get(PortalPages::url('catalog_led'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.priceList', null)
            ->where('page.canManage', true)
            ->where('page.createUrl', route('catalog.price-lists.store')));
});
