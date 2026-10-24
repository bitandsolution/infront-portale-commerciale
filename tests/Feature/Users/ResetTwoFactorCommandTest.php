<?php

use App\Models\User;
use App\Support\Navigation\PortalPages;

/*
| Server command users:reset-two-factor <e-mail> (D-276): unlocks a user without his second factor,
| also the only admin, after a confirmation. His open sessions close (D-280).
*/

const RESET_2FA_CONFIRM = "Azzerare la verifica in due passaggi? L'utente la riconfigurerà al prossimo accesso e le sue sessioni aperte verranno chiuse.";

it('D-276: resets the second factor of the only admin after the confirmation', function () {
    $admin = User::factory()->admin()->withPassword('Password-Admin-2027!')->create(['email' => 'admin@example.test']);

    $this->artisan('users:reset-two-factor', ['email' => ' Admin@Example.test '])
        ->expectsOutputToContain('admin@example.test')
        ->expectsConfirmation(RESET_2FA_CONFIRM, 'yes')
        ->expectsOutputToContain('Verifica in due passaggi azzerata per admin@example.test')
        ->assertSuccessful();

    $admin->refresh();
    expect($admin->two_factor_secret)->toBeNull()
        ->and($admin->two_factor_recovery_codes)->toBeNull()
        ->and($admin->two_factor_confirmed_at)->toBeNull();

    // He signs in with his password and configures the second factor again at once.
    $this->post(route('login.store'), ['email' => 'admin@example.test', 'password' => 'Password-Admin-2027!'])
        ->assertRedirect(PortalPages::url('dashboard'));
    $this->get(PortalPages::url('dashboard'))->assertRedirect(route('two-factor.setup'));
});

it('D-280: closes the sessions opened with the second factor just reset', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(PortalPages::url('dashboard'))->assertOk();

    $this->artisan('users:reset-two-factor', ['email' => $agent->email])
        ->expectsConfirmation(RESET_2FA_CONFIRM, 'yes')
        ->assertSuccessful();

    // Next request of the open session (the user is read again from the database).
    $agent->refresh();
    $this->get(route('two-factor.setup'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('toast.message', __('ui.auth.two_factor_reset'));
    $this->assertGuest();
});

it('D-276: changes nothing without the confirmation', function () {
    $agent = User::factory()->agent()->create();

    $this->artisan('users:reset-two-factor', ['email' => $agent->email])
        ->expectsConfirmation(RESET_2FA_CONFIRM, 'no')
        ->expectsOutputToContain('Operazione annullata')
        ->assertFailed();

    expect($agent->fresh()?->two_factor_confirmed_at)->not->toBeNull();
});

it('D-276: without interaction the answer is no', function () {
    $agent = User::factory()->agent()->create();

    $this->artisan('users:reset-two-factor', ['email' => $agent->email, '--no-interaction' => true])
        ->assertFailed();

    expect($agent->fresh()?->two_factor_confirmed_at)->not->toBeNull();
});

it('D-276: says so when the e-mail is unknown or the user has no second factor', function () {
    $agent = User::factory()->agent()->withoutTwoFactor()->create();

    $this->artisan('users:reset-two-factor', ['email' => 'nessuno@example.test'])
        ->expectsOutputToContain('Nessun utente con e-mail nessuno@example.test')
        ->assertFailed();

    $this->artisan('users:reset-two-factor', ['email' => $agent->email])
        ->expectsOutputToContain('non ha la verifica in due passaggi configurata')
        ->assertSuccessful();
});
