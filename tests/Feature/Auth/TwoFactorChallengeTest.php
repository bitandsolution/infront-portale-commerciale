<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Second factor at every login (D-136, D-214): code from the authenticator app or one of the
| 8 single-use recovery codes.
*/

const CHALLENGE_PASSWORD = 'Una-password-di-prova-2027';

/**
 * Password step of the login: leaves the user waiting for the second factor.
 */
function passPasswordStep(User $user): void
{
    test()->post(route('login.store'), ['email' => $user->email, 'password' => CHALLENGE_PASSWORD])
        ->assertRedirect(route('two-factor.login'));
}

it('D-136: shows the code page only while a login is waiting for it', function () {
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));

    passPasswordStep(User::factory()->withPassword(CHALLENGE_PASSWORD)->create());

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/TwoFactorChallenge')
            ->where('challengeUrl', route('two-factor.login.store')));
});

it('D-136: signs in with the code of the authenticator app', function () {
    $user = User::factory()->manager()->withPassword(CHALLENGE_PASSWORD)->create(['last_login_at' => null]);
    passPasswordStep($user);

    $this->post(route('two-factor.login.store'), ['code' => totpCode()])
        ->assertRedirect(route('pages.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(session('login.id'))->toBeNull()
        ->and($user->fresh()?->last_login_at)->not->toBeNull();
});

it('D-136: refuses a wrong code and stays on the code page', function () {
    $user = User::factory()->withPassword(CHALLENGE_PASSWORD)->create();
    passPasswordStep($user);

    $this->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors(['code' => __('auth.two_factor.invalid_code')]);

    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

it('D-214: accepts each recovery code only once', function () {
    $user = User::factory()->withPassword(CHALLENGE_PASSWORD)->create();
    $code = $user->recoveryCodes()[3];

    passPasswordStep($user);
    $this->post(route('two-factor.login.store'), ['recovery_code' => $code])
        ->assertRedirect(route('pages.dashboard'));
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'));
    passPasswordStep($user);

    $this->post(route('two-factor.login.store'), ['recovery_code' => $code])
        ->assertSessionHasErrors(['recovery_code' => __('auth.two_factor.invalid_recovery_code')]);
    $this->assertGuest();
    expect($user->fresh()?->recoveryCodes())->toHaveCount(8)->not->toContain($code);
});

it('D-214: asks the second factor again at every login', function () {
    $user = User::factory()->withPassword(CHALLENGE_PASSWORD)->create();

    passPasswordStep($user);
    $this->post(route('two-factor.login.store'), ['code' => totpCode()]);
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'));
    $this->assertGuest();

    passPasswordStep($user);
    $this->assertGuest();
});
