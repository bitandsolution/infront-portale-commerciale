<?php

use App\Domain\Users\Actions\DeactivateUser;
use App\Domain\Users\Actions\UpdateUser;
use App\Domain\Users\Data\UserDetailsData;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
| Deactivation and reactivation (M01 §5, RB-05), "at least one active admin" (RB-04, M01-CA5),
| deactivated agent (M01-CA4, part possible before the clients of slice F3).
| The test with two concurrent operations is in tests/Concurrency.
*/

beforeEach(function () {
    Mail::fake();
});

function detailsOf(User $user, Role $role): UserDetailsData
{
    return new UserDetailsData($user->first_name, $user->last_name, $user->email, $role, $user->phone);
}

it('RB-05: the admin deactivates a user, who loses access at once; his data stay', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create(['first_name' => 'Marta', 'last_name' => 'Gestori']);

    // The manager is signed in.
    $this->actingAs($manager)->get(PortalPages::url('dashboard'))->assertOk();
    session()->flush();

    $this->actingAs($admin)
        ->post(route('users.deactivate', $manager))
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Marta Gestori è stato disattivato');

    $manager->refresh();
    expect($manager->is_active)->toBeFalse()
        ->and($manager->deactivated_at)->not->toBeNull()
        ->and($manager->deactivated_by)->toBe($admin->id)
        ->and($manager->first_name)->toBe('Marta');

    session()->flush();
    $this->actingAs($manager)->get(PortalPages::url('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('toast.message', __('ui.auth.deactivated'));
});

it('M01 §5: the admin reactivates a user, who signs in again with password and second factor', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->inactive()->create(['deactivated_by' => $admin->id]);
    $secret = $agent->two_factor_secret;

    $this->actingAs($admin)
        ->post(route('users.reactivate', $agent))
        ->assertSessionHas('toast.message', $agent->fullName().' è stato riattivato');

    $agent->refresh();
    expect($agent->is_active)->toBeTrue()
        ->and($agent->deactivated_at)->toBeNull()
        ->and($agent->deactivated_by)->toBeNull()
        ->and($agent->two_factor_secret)->toBe($secret);
});

it('M01-CA4: a deactivated agent stays in the portal with his data and can be found in the list', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Agenti']);

    $this->actingAs($admin)->post(route('users.deactivate', $agent))->assertRedirect();

    expect(User::query()->whereKey($agent->id)->exists())->toBeTrue();
    $this->get(route('users.show', $agent))->assertOk();

    // RB-05: users are never deleted, there is no route for it.
    $deleteRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'utenti')
            && in_array('DELETE', $route->methods(), true));
    expect($deleteRoutes->all())->toBe([]);
});

it('M01-CA5: the only active admin cannot be deactivated', function () {
    $admin = User::factory()->admin()->create();
    // Another admin, deactivated: the active one is still the only one.
    $former = User::factory()->admin()->inactive()->create();

    expect(fn () => app(DeactivateUser::class)->handle($admin, $former))
        ->toThrow(UserActionNotAllowed::class, 'deve restare almeno un admin attivo');

    expect($admin->fresh()?->is_active)->toBeTrue();
});

it('M01-CA5: the only active admin cannot be moved to another role', function () {
    $admin = User::factory()->admin()->create();
    $former = User::factory()->admin()->inactive()->create();

    expect(fn () => app(UpdateUser::class)->handle($admin, detailsOf($admin, Role::Manager), $former))
        ->toThrow(UserActionNotAllowed::class, 'deve restare almeno un admin attivo');

    expect($admin->fresh()?->role)->toBe(Role::Admin);
});

it('M01-CA5: with two active admins one deactivates the other, then the last one stays', function () {
    $anna = User::factory()->admin()->create();
    $bruno = User::factory()->admin()->create(['first_name' => 'Bruno', 'last_name' => 'Admin']);

    $this->actingAs($anna)->post(route('users.deactivate', $bruno))
        ->assertSessionHas('toast.message', 'Bruno Admin è stato disattivato');

    // Anna is now the last active admin: neither herself nor anyone else can remove her.
    $this->post(route('users.deactivate', $anna))->assertForbidden();
    expect(fn () => app(DeactivateUser::class)->handle($anna, $bruno))->toThrow(UserActionNotAllowed::class);
    expect(fn () => app(UpdateUser::class)->handle($anna, detailsOf($anna, Role::Agent), $bruno))->toThrow(UserActionNotAllowed::class);

    expect(User::query()->where('role', Role::Admin->value)->where('is_active', true)->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)->all())
        ->toBe([$anna->id]);
});

it('M01-CA5: an admin can be moved to another role while another admin stays active', function () {
    $anna = User::factory()->admin()->create();
    $bruno = User::factory()->admin()->create();

    $this->actingAs($anna)
        ->put(route('users.update', $bruno), [
            'first_name' => $bruno->first_name,
            'last_name' => $bruno->last_name,
            'email' => $bruno->email,
            'phone' => '',
            'role' => 'manager',
        ])
        ->assertSessionHas('toast.message', "Dati dell'utente salvati");

    expect($bruno->fresh()?->role)->toBe(Role::Manager);
});

it('RB-05: a deactivation removes the pending access link', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'first_name' => 'Nuovo', 'last_name' => 'Gestore', 'email' => 'nuovo.gestore@example.test', 'phone' => '', 'role' => 'manager',
    ]);
    $user = User::query()->where('email', 'nuovo.gestore@example.test')->sole();

    $this->post(route('users.deactivate', $user))->assertRedirect();

    expect($this->app['db']->table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});
