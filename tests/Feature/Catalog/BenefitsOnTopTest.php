<?php

use App\Domain\Catalog\Actions\SaveOnTopAsset;
use App\Domain\Catalog\Actions\SetPartnershipBenefits;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\BenefitCategory;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\OnTopAsset;
use App\Models\PartnershipBenefit;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Benefit texts of club and tier, and on-top texts of a price list (M03 F-08, F-10, FL-03; D-049,
| D-117, D-335; M03-CA7; F2-03). Permissions of every route are in RouteMatrixTest.
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function benefitsClub(PriceList $priceList, string $name, array $state = []): Club
{
    $club = Club::factory()->infront()->create(['name' => $name, ...$state]);
    ClubEnrollment::query()->create([
        'competition_season_id' => CompetitionSeason::factory()->create(['season_id' => $priceList->season_id])->id,
        'club_id' => $club->id,
    ]);

    return $club;
}

function benefitsTier(string $code): PartnershipTier
{
    return PartnershipTier::query()->where('code', $code)->firstOrFail();
}

function benefitsCategory(string $code): BenefitCategory
{
    return BenefitCategory::query()->where('code', $code)->firstOrFail();
}

function benefitText(PriceList $priceList, Club $club, PartnershipTier $tier, BenefitCategory $category): ?string
{
    return PartnershipBenefit::query()
        ->where('price_list_id', $priceList->id)
        ->where('club_id', $club->id)
        ->where('partnership_tier_id', $tier->id)
        ->where('benefit_category_id', $category->id)
        ->value('body');
}

/* Benefits (F-08, D-335). */

it('M03-CA7: a benefit changed for Lazio Top does not change other clubs or tiers', function () {
    $priceList = PriceList::factory()->create();
    $lazio = benefitsClub($priceList, 'SS Lazio', ['code' => 'LAZIO']);
    $other = benefitsClub($priceList, 'Altro club');
    $ip = benefitsCategory('IP_RIGHTS');
    $action = app(SetPartnershipBenefits::class);
    $action->handle($priceList, $lazio, benefitsTier('PREMIUM'), [$ip->id => 'Testo Premium'], $this->admin);
    $action->handle($priceList, $other, benefitsTier('TOP'), [$ip->id => 'Testo altro club'], $this->admin);

    $this->actingAs($this->admin)
        ->put(route('catalog.benefits.update', [$priceList, $lazio]), [
            'partnership_tier_id' => benefitsTier('TOP')->id,
            'texts' => [$ip->id => "  Uso del marchio\ne del logo  ", benefitsCategory('NOTES')->id => ''],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Benefit di SS Lazio, Top salvati');

    expect(benefitText($priceList, $lazio, benefitsTier('TOP'), $ip))->toBe("Uso del marchio\ne del logo")
        ->and(benefitText($priceList, $lazio, benefitsTier('PREMIUM'), $ip))->toBe('Testo Premium')
        ->and(benefitText($priceList, $other, benefitsTier('TOP'), $ip))->toBe('Testo altro club')
        ->and(benefitText($priceList, $lazio, benefitsTier('TOP'), benefitsCategory('NOTES')))->toBeNull();

    $event = auditEventsOf(AuditAction::PartnershipBenefitUpdated)->last();
    expect($event?->object_label)->toBe("Listino {$priceList->season->code} · SS Lazio (LAZIO) · Top (TOP) · IP rights")
        ->and($event?->old_values)->toBe(['body' => null])
        ->and($event?->new_values)->toBe(['body' => "Uso del marchio\ne del logo"]);
});

it('D-335: an empty text removes the benefit; the same texts write nothing', function () {
    $priceList = PriceList::factory()->published()->create();
    $club = benefitsClub($priceList, 'Alfa');
    $tier = benefitsTier('OFFICIAL');
    $category = benefitsCategory('SERVICES');
    $action = app(SetPartnershipBenefits::class);

    expect($action->handle($priceList, $club, $tier, [$category->id => 'Servizi dedicati'], $this->admin))->toBe(1)
        ->and($action->handle($priceList, $club, $tier, [$category->id => 'Servizi dedicati'], $this->admin))->toBe(0)
        ->and($action->handle($priceList, $club, $tier, [$category->id => '   '], $this->admin))->toBe(1)
        ->and(PartnershipBenefit::query()->count())->toBe(0);

    $events = auditEventsOf(AuditAction::PartnershipBenefitUpdated);
    expect($events)->toHaveCount(2)
        ->and($events[1]->old_values)->toBe(['body' => 'Servizi dedicati'])
        ->and($events[1]->new_values)->toBe(['body' => null]);
});

it('RB-04: the free package "Libera" has no benefits', function () {
    $priceList = PriceList::factory()->create();
    $club = benefitsClub($priceList, 'Beta');

    expect(fn () => app(SetPartnershipBenefits::class)->handle($priceList, $club, benefitsTier('LIBERA'), [benefitsCategory('NOTES')->id => 'x'], $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, 'Il pacchetto Libera non ha prezzo né benefit');
});

it('M03 §8: a benefit text has at most 5000 characters', function () {
    $priceList = PriceList::factory()->create();
    $club = benefitsClub($priceList, 'Gamma');
    $category = benefitsCategory('NOTES');

    $this->actingAs($this->admin)
        ->put(route('catalog.benefits.update', [$priceList, $club]), [
            'partnership_tier_id' => benefitsTier('TOP')->id,
            'texts' => [$category->id => str_repeat('a', 5001)],
        ])
        ->assertSessionHasErrors("texts.{$category->id}");

    expect(PartnershipBenefit::query()->count())->toBe(0);
});

it('D-335: the page of a club shows a text per tier and category, editable only by the admin', function () {
    $season = Season::factory()->open()->create();
    $priceList = PriceList::factory()->create(['season_id' => $season->id]);
    $club = benefitsClub($priceList, 'Delta');
    app(SetPartnershipBenefits::class)->handle($priceList, $club, benefitsTier('TOP'), [benefitsCategory('IP_RIGHTS')->id => 'Marchio'], $this->admin);

    $this->actingAs($this->admin)
        ->get(route('catalog.benefits.show', [$priceList, $club]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Benefits/Show')
            ->where('club.name', 'Delta')
            ->where('canManage', true)
            ->has('tiers', 4)
            ->where('tiers.0.name', 'Top')
            ->has('tiers.0.categories', 8)
            ->where('tiers.0.categories.0', ['id' => benefitsCategory('IP_RIGHTS')->id, 'name' => 'IP rights', 'body' => 'Marchio'])
            ->where('tiers.1.categories.0.body', '')
            ->where('onTop.clubId', $club->id));

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('catalog.benefits.show', [$priceList, $club]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

/* On-top texts (F-10, D-049, D-335). */

it('D-335: the admin adds a general on-top text and one of a club, each after the others, with audit events', function () {
    $priceList = PriceList::factory()->create();
    $club = benefitsClub($priceList, 'Epsilon', ['code' => 'EPSILON']);

    $this->actingAs($this->admin)
        ->post(route('catalog.on-top.store', $priceList), ['title' => 'Match sponsor', 'body' => 'Una partita intitolata al cliente'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Testo on top Match sponsor aggiunto');

    $this->post(route('catalog.on-top.store', $priceList), ['club_id' => $club->id, 'title' => 'Branded content', 'body' => 'Contenuti social'])
        ->assertSessionHasNoErrors();
    $this->post(route('catalog.on-top.store', $priceList), ['club_id' => $club->id, 'title' => 'Attivazioni', 'body' => 'Attivazioni allo stadio'])
        ->assertSessionHasNoErrors();

    $general = OnTopAsset::query()->whereNull('club_id')->sole();
    $ofClub = OnTopAsset::query()->where('club_id', $club->id)->orderBy('sort_order')->get();

    expect($general->sort_order)->toBe(0)
        ->and($ofClub->pluck('title')->all())->toBe(['Branded content', 'Attivazioni'])
        ->and($ofClub->pluck('sort_order')->all())->toBe([0, 1]);

    $events = auditEventsOf(AuditAction::OnTopAssetCreated);
    expect($events)->toHaveCount(3)
        ->and($events[0]->object_label)->toBe("Listino {$priceList->season->code} · Generale · Match sponsor")
        ->and($events[0]->new_values)->toBe(['club' => null, 'title' => 'Match sponsor', 'body' => 'Una partita intitolata al cliente'])
        ->and($events[1]->object_label)->toBe("Listino {$priceList->season->code} · Epsilon (EPSILON) · Branded content");
});

it('D-335: the admin changes and removes an on-top text, with audit events', function () {
    $priceList = PriceList::factory()->create();
    $asset = app(SaveOnTopAsset::class)->create($priceList, null, 'Match sponsor', 'Testo', $this->admin);

    $this->actingAs($this->admin)
        ->put(route('catalog.on-top.update', $asset), ['title' => 'Match sponsor', 'body' => 'Testo nuovo'])
        ->assertSessionHas('toast.message', 'Testo on top Match sponsor salvato');

    expect(soleAuditEvent(AuditAction::OnTopAssetUpdated)->old_values)->toBe(['body' => 'Testo']);

    $this->put(route('catalog.on-top.update', $asset), ['title' => 'Match sponsor', 'body' => 'Testo nuovo'])
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');

    $this->delete(route('catalog.on-top.destroy', $asset))
        ->assertSessionHas('toast.message', 'Testo on top Match sponsor tolto');

    expect(OnTopAsset::query()->count())->toBe(0)
        ->and(soleAuditEvent(AuditAction::OnTopAssetRemoved)->old_values)->toBe(['club' => null, 'title' => 'Match sponsor', 'body' => 'Testo nuovo']);
});

it('M03 §8: an on-top text needs a title and a text', function () {
    $priceList = PriceList::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('catalog.on-top.store', $priceList), ['title' => ' ', 'body' => ''])
        ->assertSessionHasErrors(['title', 'body']);

    expect(OnTopAsset::query()->count())->toBe(0);
});

it('D-337: on-top texts belong to the clubs of the grid', function () {
    $priceList = PriceList::factory()->create();
    $opponent = Club::factory()->create(['name' => 'Avversario']);

    expect(fn () => app(SaveOnTopAsset::class)->create($priceList, $opponent, 'Titolo', 'Testo', $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, 'Avversario non è un club Infront attivo');
});

it('D-333: benefits and on-top texts of a closed season are read-only', function () {
    $season = Season::factory()->open()->create();
    $priceList = PriceList::factory()->create(['season_id' => $season->id]);
    $club = benefitsClub($priceList, 'Zeta');
    $asset = app(SaveOnTopAsset::class)->create($priceList, null, 'Match sponsor', 'Testo', $this->admin);
    $season->update(['status' => 'closed']);
    $message = "La stagione {$season->code} è chiusa: il suo listino è in sola lettura";

    expect(fn () => app(SetPartnershipBenefits::class)->handle($priceList, $club, benefitsTier('TOP'), [benefitsCategory('NOTES')->id => 'x'], $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, $message)
        ->and(fn () => app(SaveOnTopAsset::class)->update($asset, 'Nuovo', 'Testo', $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, $message)
        ->and(fn () => app(SaveOnTopAsset::class)->remove($asset, $this->admin))
        ->toThrow(CatalogActionNotAllowed::class, $message);

    expect(OnTopAsset::query()->count())->toBe(1);
});
