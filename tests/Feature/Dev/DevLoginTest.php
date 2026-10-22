<?php

use App\Models\User;
use App\Support\DevLogin;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Development access (D-268): local and testing environments only, removed by PR-03.
*/

it('D-268: is enabled only in local (and testing) with DEV_LOGIN=true', function (string $environment, bool $flag, bool $enabled) {
    app()->detectEnvironment(fn () => $environment);
    config(['app.dev_login' => $flag]);

    expect(DevLogin::enabled())->toBe($enabled);
})->with([
    'local, flag on' => ['local', true, true],
    'local, flag off' => ['local', false, false],
    'testing, flag on' => ['testing', true, true],
    'staging, flag on' => ['staging', true, false],
    'production, flag on' => ['production', true, false],
    'production, flag off' => ['production', false, false],
]);

it('D-268: answers 404 in staging and production even with DEV_LOGIN=true', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);
    config(['app.dev_login' => true]);
    $agent = User::factory()->agent()->create();

    $this->get('/sviluppo/accesso')->assertNotFound();

    // Outside "testing" Laravel checks the CSRF token again: send a valid one.
    $this->withSession(['_token' => 'dev-login-token'])
        ->post('/sviluppo/accesso/'.$agent->id, ['_token' => 'dev-login-token'])
        ->assertNotFound();

    $this->assertGuest();
})->with(['staging', 'production']);

it('D-268: answers 404 on the local machine when DEV_LOGIN is off', function () {
    config(['app.dev_login' => false]);

    $this->get('/sviluppo/accesso')->assertNotFound();
});

it('D-268: lists the users of the database by role', function () {
    User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Agenti']);
    User::factory()->admin()->create(['first_name' => 'Laura', 'last_name' => 'Verdi']);
    User::factory()->manager()->inactive()->create(['first_name' => 'Marta', 'last_name' => 'Gestori']);

    $this->get(route('dev.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dev/Login')
            ->has('users', 3)
            ->where('users.0.name', 'Laura Verdi')
            ->where('users.0.role', 'Admin')
            ->where('users.1.name', 'Marta Gestori')
            ->where('users.1.active', false)
            ->where('users.2.role', 'Agente')
            ->where('current', null)
        );
});

it('D-268: signs in as the chosen user and opens his dashboard with the menu of his role', function () {
    $manager = User::factory()->manager()->create(['first_name' => 'Marta', 'last_name' => 'Gestori']);

    $this->post(route('dev.login.store', $manager))->assertRedirect(route('pages.dashboard'));
    $this->assertAuthenticatedAs($manager);

    $this->get(route('pages.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Placeholder')
            ->where('layout.title', 'Dashboard')
            ->where('layout.user.name', 'Marta Gestori')
            ->where('layout.user.initials', 'MG')
            ->where('layout.user.role', 'Gestore')
            ->where('layout.navigation', fn ($items) => collect($items)->pluck('key')->contains('catalog')
                && collect($items)->pluck('key')->doesntContain('users'))
            ->where('devLoginUrl', route('dev.login'))
        );
});

it('RB-05: a deactivated user signed in through the development access is sent back at once', function () {
    $inactive = User::factory()->agent()->inactive()->create();

    $this->post(route('dev.login.store', $inactive))->assertRedirect(route('pages.dashboard'));

    $this->get(route('pages.dashboard'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('toast.message', "Il tuo account è disattivato. Per riattivarlo rivolgiti all'amministratore");

    $this->assertGuest();
});

it('D-268: signs out', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->post(route('dev.logout'))->assertRedirect(route('dev.login'));

    $this->assertGuest();
});

it('D-268: the home page links the development access only where it exists', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('devLoginUrl', route('dev.login')));

    config(['app.dev_login' => false]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('devLoginUrl', null));
});
