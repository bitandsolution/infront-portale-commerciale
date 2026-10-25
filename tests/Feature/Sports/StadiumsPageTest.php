<?php

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\Stadium;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Stadiums page (M02 F-07; D-137: admin and manager; C4 never deleted). Permissions of every route
| are in the route × role matrix (RouteMatrixTest).
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stadiumsForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Stadio Comunale di Prova',
        'city' => 'Trento',
        'capacity' => '12500',
        'is_active' => true,
    ], $overrides);
}

it('F-07: the manager sees the stadiums with city, capacity and clubs', function () {
    $stadium = Stadium::factory()->create(['name' => 'Stadio Alfa', 'city' => 'Trento', 'capacity' => 12500]);
    Club::factory()->create(['home_stadium_id' => $stadium->id]);

    $this->actingAs(User::factory()->manager()->create())
        ->get(PortalPages::url('sports_stadiums'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Stadiums/Index')
            ->has('stadiums', 1)
            ->where('stadiums.0.name', 'Stadio Alfa')
            ->where('stadiums.0.capacity', 12500)
            ->where('stadiums.0.clubs', 1));
});

it('F-07, D-127: the manager creates a stadium, with an audit event', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->post(route('sports.stadiums.store'), stadiumsForm())
        ->assertRedirect(PortalPages::url('sports_stadiums'))
        ->assertSessionHas('toast.message', 'Stadio Stadio Comunale di Prova creato');

    $stadium = Stadium::query()->sole();
    expect($stadium->capacity)->toBe(12500);

    $event = soleAuditEvent(AuditAction::StadiumCreated);
    expect($event->area)->toBe('sports')
        ->and($event->user_role)->toBe('manager')
        ->and($event->object_label)->toBe('Stadio Comunale di Prova (Trento)')
        ->and($event->new_values)->toBe(['name' => 'Stadio Comunale di Prova', 'city' => 'Trento', 'capacity' => 12500, 'is_active' => true]);
});

it('F-07: the capacity is optional, but a whole number greater than zero when given', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('sports.stadiums.store'), stadiumsForm(['capacity' => '']))->assertSessionHasNoErrors();
    expect(Stadium::query()->sole()->capacity)->toBeNull();

    $this->actingAs($admin)
        ->post(route('sports.stadiums.store'), stadiumsForm(['capacity' => '0']))
        ->assertSessionHasErrors(['capacity' => 'Il campo capienza deve essere compreso tra 1 e 200000.']);
});

it('C4: a stadium is deactivated, with only the changes in the audit', function () {
    $stadium = Stadium::factory()->create(['name' => 'Stadio Comunale di Prova', 'city' => 'Trento', 'capacity' => 12500]);

    $this->actingAs(User::factory()->manager()->create())
        ->put(route('sports.stadiums.update', $stadium), stadiumsForm(['is_active' => false]))
        ->assertSessionHas('toast.message', 'Stadio Stadio Comunale di Prova salvato');

    expect($stadium->refresh()->is_active)->toBeFalse();

    $event = soleAuditEvent(AuditAction::StadiumUpdated);
    expect($event->old_values)->toBe(['is_active' => true])
        ->and($event->new_values)->toBe(['is_active' => false]);
});

it('F-07: saving the same values writes nothing', function () {
    $stadium = Stadium::factory()->create(['name' => 'Stadio Comunale di Prova', 'city' => 'Trento', 'capacity' => 12500]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.stadiums.update', $stadium), stadiumsForm())
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');

    expect(AuditEvent::query()->count())->toBe(0);
});
