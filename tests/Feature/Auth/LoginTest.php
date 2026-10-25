<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Login with e-mail and password (D-135, D-214; progettazione-tecnica §5.4.2).
*/

const LOGIN_PASSWORD = 'Una-password-di-prova-2027';

it('D-214: shows the login page of the portal', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('loginUrl', route('login.store'))
            ->where('lockedSeconds', 0)
            ->where('app.locale', 'it'));
});

it('D-269: sends a guest asking for a page to the login, and back to that page afterwards', function () {
    $user = User::factory()->manager()->withoutTwoFactor()->withPassword(LOGIN_PASSWORD)->create();

    $this->get(route('pages.catalog_led'))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOGIN_PASSWORD])
        ->assertRedirect(route('pages.catalog_led'));
});

it('D-214: gives the same generic message for a wrong e-mail and a wrong password', function () {
    User::factory()->withPassword(LOGIN_PASSWORD)->create(['email' => 'luca.agenti@example.test']);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => 'nessuno@example.test', 'password' => LOGIN_PASSWORD])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => 'luca.agenti@example.test', 'password' => 'password-sbagliata'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('D-214: accepts the e-mail in any letter case', function () {
    $user = User::factory()->withoutTwoFactor()->withPassword(LOGIN_PASSWORD)->create(['email' => 'sara.agenti@example.test']);

    $this->post(route('login.store'), ['email' => '  Sara.Agenti@Example.TEST', 'password' => LOGIN_PASSWORD]);

    $this->assertAuthenticatedAs($user);
});

it('RB-05: refuses a deactivated user with the dedicated message only when the password is right', function () {
    $user = User::factory()->inactive()->withPassword(LOGIN_PASSWORD)->create();

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => LOGIN_PASSWORD])
        ->assertSessionHasErrors(['email' => __('ui.auth.deactivated')]);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password-sbagliata'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
    expect(session('login.id'))->toBeNull();
});

it('D-136: asks the second factor before signing in a user who configured it', function () {
    $user = User::factory()->withPassword(LOGIN_PASSWORD)->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOGIN_PASSWORD])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

it('D-214: regenerates the session and records the last login', function () {
    $user = User::factory()->withoutTwoFactor()->withPassword(LOGIN_PASSWORD)->create(['last_login_at' => null]);

    $this->get(route('login'));
    $before = session()->getId();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOGIN_PASSWORD]);

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before)
        ->and($user->fresh()?->last_login_at)->not->toBeNull();
});

it('EV-23: ignores "remember me", so no cookie can skip the login', function () {
    $user = User::factory()->withoutTwoFactor()->withPassword(LOGIN_PASSWORD)->create(['remember_token' => null]);

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => LOGIN_PASSWORD, 'remember' => '1']);

    expect(collect($response->headers->getCookies())->map->getName()->filter(fn (string $name): bool => str_starts_with($name, 'remember_')))->toBeEmpty()
        ->and(Auth::viaRemember())->toBeFalse()
        ->and($user->fresh()?->remember_token)->toBeNull();
});

it('signs out from the user menu and closes the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('pages.dashboard'))->assertRedirect(route('login'));
});

it('D-214: hashes passwords with Argon2id', function () {
    expect(Hash::make(LOGIN_PASSWORD))->toStartWith('$argon2id$')
        ->and(Hash::info(Hash::make(LOGIN_PASSWORD))['algoName'])->toBe('argon2id');
});

it('D-268, D-264: the development access and the layout preview no longer exist', function (string $method, string $uri) {
    $this->call($method, $uri)->assertNotFound();
})->with([
    ['GET', '/sviluppo/accesso'],
    ['POST', '/sviluppo/esci'],
    ['GET', '/anteprima/layout/agente'],
    ['POST', '/anteprima/errore'],
]);

// The health endpoint is /health (PR-09): tests/Feature/Operations/HealthCheckTest.php.
