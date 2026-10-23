<?php

use App\Models\User;
use App\Support\Navigation\PortalPages;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/*
| Mandatory setup of the second factor (D-136, M01-CA1; progettazione-tecnica §5.4.2):
| until a code is confirmed every page leads to the setup; the 8 recovery codes are shown once.
*/

function setupSecret(User $user): string
{
    $secret = Fortify::currentEncrypter()->decrypt((string) $user->fresh()?->two_factor_secret);

    return is_string($secret) ? $secret : '';
}

it('M01-CA1: an agent without the second factor cannot open any page of the portal', function (string $key) {
    $agent = User::factory()->agent()->withoutTwoFactor()->create();

    $this->actingAs($agent)
        ->get(PortalPages::url($key))
        ->assertRedirect(route('two-factor.setup'));
})->with(array_keys(PortalPages::PAGES));

it('M01-CA1: after the password an agent without the second factor lands on the setup', function () {
    $agent = User::factory()->agent()->withoutTwoFactor()->withPassword('Una-password-di-prova-2027')->create();

    $this->post(route('login.store'), ['email' => $agent->email, 'password' => 'Una-password-di-prova-2027'])
        ->assertRedirect(route('pages.dashboard'));
    $this->get(route('pages.dashboard'))->assertRedirect(route('two-factor.setup'));
});

it('D-136: shows the QR code and the key, the same at every reload', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $first = $this->actingAs($user)->get(route('two-factor.setup'));
    $first->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/TwoFactorSetup')
        ->where('qrCode', fn (string $uri): bool => str_starts_with($uri, 'data:image/svg+xml;base64,'))
        ->where('secret', fn (string $secret): bool => str_replace(' ', '', $secret) === setupSecret($user))
        ->where('account', $user->email));

    $secret = setupSecret($user);
    $this->get(route('two-factor.setup'))->assertOk();

    expect(setupSecret($user))->toBe($secret)
        ->and($user->fresh()?->two_factor_confirmed_at)->toBeNull();
});

it('D-136: refuses a wrong code and keeps the account without the second factor', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($user)->get(route('two-factor.setup'));

    $this->from(route('two-factor.setup'))
        ->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.setup'))
        ->assertSessionHasErrors(['code' => __('auth.two_factor.invalid_code')]);

    expect($user->fresh()?->two_factor_confirmed_at)->toBeNull();
    $this->get(route('pages.dashboard'))->assertRedirect(route('two-factor.setup'));
});

it('D-214: confirms the code and shows the 8 recovery codes only once', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($user)->get(route('two-factor.setup'));

    $code = app(Google2FA::class)->getCurrentOtp(setupSecret($user));

    $this->post(route('two-factor.confirm'), ['code' => $code])
        ->assertRedirect(route('two-factor.recovery-codes'));

    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull();

    $this->get(route('two-factor.recovery-codes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/RecoveryCodes')
            ->has('codes', 8)
            ->where('codes', $user->recoveryCodes())
            ->where('continueUrl', route('pages.dashboard')));

    // Reloading the page does not show them again.
    $this->get(route('two-factor.recovery-codes'))->assertRedirect(route('pages.dashboard'));
    $this->get(route('pages.dashboard'))->assertOk();
});

it('D-214: gives 8 different recovery codes to every account', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($user)->get(route('two-factor.setup'));

    $codes = $user->fresh()?->recoveryCodes();

    expect($codes)->toHaveCount(8)
        ->and(array_unique($codes))->toHaveCount(8);
});

it('D-136: sends a user who already has the second factor away from the setup', function () {
    $user = User::factory()->create();
    $secret = $user->two_factor_secret;

    $this->actingAs($user)->get(route('two-factor.setup'))->assertRedirect(route('pages.dashboard'));
    $this->post(route('two-factor.confirm'), ['code' => totpCode()])->assertRedirect(route('pages.dashboard'));

    expect($user->fresh()?->two_factor_secret)->toBe($secret);
});

it('D-136: lets a user without the second factor sign out', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});
