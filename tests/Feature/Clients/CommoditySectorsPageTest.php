<?php

use App\Domain\Clients\Actions\CreateCommoditySector;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\CommoditySector;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Sectors page (M05 F-05; D-357: admin only; RB-08 never deleted). Permissions of every route are in
| the route × role matrix (RouteMatrixTest).
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sectorsForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sport & Fitness',
        'sort_order' => '250',
        'is_active' => true,
    ], $overrides);
}

it('F-05: the admin sees the sectors in their order, with code and free-text flag', function () {
    CommoditySector::factory()->create(['code' => 'ZETA', 'name' => 'Zeta', 'sort_order' => 20]);
    CommoditySector::factory()->other()->create(['sort_order' => 10]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('clients_sectors'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Sectors/Index')
            ->has('sectors', 2)
            ->where('sectors.0.code', 'ALTRO_VARIE')
            ->where('sectors.0.requiresFreeText', true)
            ->where('sectors.1.code', 'ZETA')
            ->where('sectors.1.requiresFreeText', false));
});

it('F-05, D-357: the admin creates a sector, with a code from the name and an audit event', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('clients.sectors.store'), sectorsForm())
        ->assertRedirect(PortalPages::url('clients_sectors'))
        ->assertSessionHas('toast.message', 'Settore Sport & Fitness creato');

    $sector = CommoditySector::query()->sole();
    expect($sector->code)->toBe('SPORT_FITNESS')
        ->and($sector->requires_free_text)->toBeFalse()
        ->and($sector->sort_order)->toBe(250);

    $event = soleAuditEvent(AuditAction::CommoditySectorCreated);
    expect($event->area)->toBe('clients')
        ->and($event->user_role)->toBe('admin')
        ->and($event->object_label)->toBe('Sport & Fitness (SPORT_FITNESS)')
        ->and($event->new_values)->toBe(['commodity_sector_code' => 'SPORT_FITNESS', 'name' => 'Sport & Fitness', 'sort_order' => 250, 'is_active' => true]);
});

it('D-357: the code drops accents, starts with a letter and gets a number when already used', function () {
    CommoditySector::factory()->create(['code' => 'ATTIVITA_SPORTIVE', 'name' => 'Attività sportive']);

    expect(CreateCommoditySector::codeFor('Attività sportive bis'))->toBe('ATTIVITA_SPORTIVE_BIS')
        ->and(CreateCommoditySector::codeFor('attività  sportive!'))->toBe('ATTIVITA_SPORTIVE_2')
        ->and(CreateCommoditySector::codeFor('3D e realtà virtuale'))->toBe('SETTORE_3D_E_REALTA_VIRTUALE')
        ->and(strlen(CreateCommoditySector::codeFor(str_repeat('Settore molto lungo ', 5))))->toBeLessThanOrEqual(32);
});

it('F-05: two sectors cannot have the same name, whatever the case and the accents', function () {
    CommoditySector::factory()->create(['name' => 'Attività sportive']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('clients.sectors.store'), sectorsForm(['name' => 'ATTIVITA SPORTIVE']))
        ->assertSessionHasErrors(['name' => 'Il valore del campo nome è già in uso.']);

    expect(CommoditySector::query()->count())->toBe(1);
});

it('RB-08: a sector is deactivated, with only the changes in the audit; the code never changes', function () {
    $sector = CommoditySector::factory()->create(['code' => 'ABBIGLIAMENTO', 'name' => 'Abbigliamento', 'sort_order' => 10]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('clients.sectors.update', $sector), sectorsForm(['name' => 'Abbigliamento e moda', 'sort_order' => '10', 'is_active' => false]))
        ->assertSessionHas('toast.message', 'Settore Abbigliamento e moda salvato');

    $sector->refresh();
    expect($sector->is_active)->toBeFalse()
        ->and($sector->code)->toBe('ABBIGLIAMENTO');

    $event = soleAuditEvent(AuditAction::CommoditySectorUpdated);
    expect($event->old_values)->toBe(['name' => 'Abbigliamento', 'is_active' => true])
        ->and($event->new_values)->toBe(['name' => 'Abbigliamento e moda', 'is_active' => false]);
});

it('F-05: saving the same values writes nothing', function () {
    $sector = CommoditySector::factory()->create(['name' => 'Sport & Fitness', 'sort_order' => 250]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('clients.sectors.update', $sector), sectorsForm())
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');

    expect(AuditEvent::query()->count())->toBe(0);
});

it('F-05: name and order are required, the order between 0 and 9999', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('clients.sectors.store'), sectorsForm(['name' => '', 'sort_order' => '10000']))
        ->assertSessionHasErrors(['name', 'sort_order']);

    expect(CommoditySector::query()->count())->toBe(0);
});
