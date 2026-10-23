<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
| Sessions (M01 §6, D-214): expiry by role after inactivity (8 h agent, 2 h admin and manager),
| a password change closes the other sessions, Redis keeps the session for the longest limit.
*/

it('D-214: expires the session of each role after its inactivity limit', function (Role $role, int $minutes) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('pages.dashboard'))->assertOk();

    $this->travel($minutes - 1)->minutes();
    $this->get(route('pages.dashboard'))->assertOk();

    // The limit counts from the last request, not from the login.
    $this->travel($minutes + 1)->minutes();
    $this->get(route('pages.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('toast.message', __('ui.auth.session_expired'));
    $this->assertGuest();
})->with([
    'agente, 8 ore' => [Role::Agent, 480],
    'gestore, 2 ore' => [Role::Manager, 120],
    'admin, 2 ore' => [Role::Admin, 120],
]);

it('ux §4.5: after an expired session the login goes back to the page that was open', function () {
    $user = User::factory()->manager()->withoutTwoFactor()->withPassword('Una-password-di-prova-2027')->create();

    $this->actingAs($user)->get(route('pages.content_kpi'))->assertOk();
    $this->travel(121)->minutes();
    $this->get(route('pages.content_kpi'))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Una-password-di-prova-2027'])
        ->assertRedirect(route('pages.content_kpi'));
});

it('D-214: closes the other sessions when the password changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pages.dashboard'))->assertOk();

    // Change made elsewhere (from PR-04: profile, reset by the admin, reset link).
    $user->forceFill(['password' => Hash::make('Una-nuova-password-2027')])->save();

    $this->get(route('pages.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('D-214: keeps the session in Redis for the longest limit of the roles', function () {
    $env = (string) file_get_contents(base_path('.env.example'));

    expect($env)->toContain("SESSION_DRIVER=redis\n")
        ->and($env)->toContain("CACHE_STORE=redis\n")
        ->and($env)->toContain('SESSION_LIFETIME='.max(array_map(fn (Role $role): int => $role->idleTimeoutMinutes(), Role::cases()))."\n")
        ->and(config('session.lifetime'))->toBeGreaterThanOrEqual(Role::Agent->idleTimeoutMinutes());
});

it('RB-05: closes the session of a user deactivated while signed in', function () {
    $user = User::factory()->manager()->create();

    $this->actingAs($user)->get(route('pages.dashboard'))->assertOk();

    $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    $this->get(route('pages.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('toast.message', __('ui.auth.deactivated'));
    $this->assertGuest();
});
