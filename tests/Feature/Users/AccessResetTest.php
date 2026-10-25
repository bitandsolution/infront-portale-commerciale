<?php

use App\Enums\AccessLinkType;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

/*
| Resets started by the admin from the Users page (M01 §5; D-140, D-279, D-280):
| - password: the old one stops working, sessions close, link to choose a new one confirmed with
|   the code of the app;
| - second factor: secret, codes and password stop working, sessions close, link to set password
|   and second factor again on the activation page.
*/

const OLD_PASSWORD = 'Vecchia-Password-2027!';
const NEW_PASSWORD = 'Nuova-Password-Stadio-2027!';

beforeEach(function () {
    Mail::fake();
    fakePasswordArchive();
});

/**
 * The admin runs an action of the Users page and sees the toast; then the browser is the user's
 * again (the toast is checked before the logout, which clears the session).
 */
function adminRuns(object $test, string $route, User $target, ?string $toast = null): void
{
    $admin = User::factory()->admin()->create();
    $test->actingAs($admin);

    $response = $test->post(route($route, $target))->assertRedirect();

    if ($toast !== null) {
        $response->assertSessionHas('toast.message', $toast);
    }

    $test->post(route('logout'));
}

it('D-279: the password reset by the admin stops working at once and closes the sessions of the user', function () {
    $agent = User::factory()->agent()->withPassword(OLD_PASSWORD)->create();

    // The agent is signed in on his tablet.
    $this->actingAs($agent)->get(PortalPages::url('dashboard'))->assertOk();
    $tablet = session()->all();

    // Meanwhile, in another browser, the admin resets the password.
    session()->flush();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('users.reset-password', $agent))
        ->assertSessionHas('toast.message', 'Password azzerata: il link per sceglierne una nuova è stato inviato a '.$agent->email);

    expect(Hash::check(OLD_PASSWORD, (string) $agent->fresh()?->password))->toBeFalse();
    expect(sentAccessLink($agent->email, AccessLinkType::PasswordReset))->toStartWith(url('nuova-password').'/');

    // Back on the tablet: the session is closed (auth.session, D-273).
    session()->flush();
    session()->put($tablet);
    $agent->refresh();
    $this->actingAs($agent)->get(PortalPages::url('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('D-279: from the link the user chooses a new password, confirms it with the app code and enters', function () {
    $agent = User::factory()->agent()->withPassword(OLD_PASSWORD)->create();
    adminRuns($this, 'users.reset-password', $agent);
    $url = sentAccessLink($agent->email, AccessLinkType::PasswordReset);

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/ResetPassword')
        ->where('account', $agent->email));

    $this->post($url, ['password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD, 'code' => totpCode()])
        ->assertRedirect(PortalPages::url('dashboard'))
        ->assertSessionHas('toast.message', 'Nuova password impostata: bentornato nel portale');

    $agent->refresh();
    $this->assertAuthenticatedAs($agent);
    expect(Hash::check(NEW_PASSWORD, $agent->password))->toBeTrue()
        // The second factor does not change.
        ->and($agent->two_factor_confirmed_at)->not->toBeNull();

    // Once only.
    $this->post(route('logout'));
    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
});

it('D-279: a wrong app code keeps the link valid and changes nothing', function () {
    $agent = User::factory()->agent()->create();
    adminRuns($this, 'users.reset-password', $agent);
    $url = sentAccessLink($agent->email, AccessLinkType::PasswordReset);
    $hash = $agent->fresh()?->password;

    $this->from($url)
        ->post($url, ['password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD, 'code' => '000000'])
        ->assertRedirect($url)
        ->assertSessionHasErrors(['code' => __('auth.two_factor.invalid_code')]);

    expect($agent->fresh()?->password)->toBe($hash);
    $this->assertGuest();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword'));
});

it('D-279: the password reset is only for a user with the second factor', function () {
    $pending = User::factory()->agent()->withoutTwoFactor()->create(['activated_at' => null]);

    adminRuns($this, 'users.reset-password', $pending,
        "L'utente non ha ancora configurato la verifica in due passaggi: invia di nuovo il link di accesso");

    Mail::assertNothingOutgoing();
});

it('D-280: the reset of the second factor clears secret, codes and password and closes the sessions', function () {
    $agent = User::factory()->agent()->withPassword(OLD_PASSWORD)->create();

    $this->actingAs($agent)->get(PortalPages::url('dashboard'))->assertOk();
    $phone = session()->all();

    // Meanwhile, in another browser, the admin resets the second factor.
    session()->flush();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('users.reset-two-factor', $agent))
        ->assertSessionHas('toast.message', "Verifica in due passaggi azzerata: il link per configurare di nuovo l'accesso è stato inviato a ".$agent->email);

    $agent->refresh();
    expect($agent->two_factor_secret)->toBeNull()
        ->and($agent->two_factor_recovery_codes)->toBeNull()
        ->and($agent->two_factor_confirmed_at)->toBeNull()
        ->and(Hash::check(OLD_PASSWORD, $agent->password))->toBeFalse();
    sentAccessLink($agent->email, AccessLinkType::TwoFactorReset);

    // The session left open on the lost smartphone is closed: it cannot configure a new factor.
    session()->flush();
    session()->put($phone);
    $this->actingAs($agent)->get(route('two-factor.setup'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('D-280: whoever knows only the old password cannot configure the second factor in place of the user', function () {
    $agent = User::factory()->agent()->withPassword(OLD_PASSWORD)->create();
    adminRuns($this, 'users.reset-two-factor', $agent);

    $this->post(route('login.store'), ['email' => $agent->email, 'password' => OLD_PASSWORD])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

it('D-280: from the link the user sets password and second factor again, the account stays the same', function () {
    $agent = User::factory()->agent()->create();
    $activatedAt = $agent->activated_at?->getTimestamp();
    adminRuns($this, 'users.reset-two-factor', $agent);
    $url = sentAccessLink($agent->email, AccessLinkType::TwoFactorReset);

    $secret = '';
    $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$secret): void {
        $page->component('Auth/Activate')
            ->where('renewal', true)
            ->where('secret', function (string $value) use (&$secret): bool {
                $secret = str_replace(' ', '', $value);

                return $secret !== '';
            });
    });

    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->post($url, ['password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD, 'code' => $code])
        ->assertRedirect(route('two-factor.recovery-codes'));

    $agent->refresh();
    expect(Hash::check(NEW_PASSWORD, $agent->password))->toBeTrue()
        ->and($agent->two_factor_confirmed_at)->not->toBeNull()
        ->and($agent->activated_at?->getTimestamp())->toBe($activatedAt);
});

it('D-281: the admin does not reset his own access from the Users page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);
    $this->post(route('users.reset-password', $admin))->assertForbidden();
    $this->post(route('users.reset-two-factor', $admin))->assertForbidden();
    $this->post(route('users.resend-link', $admin))->assertForbidden();

    expect($admin->fresh()?->two_factor_confirmed_at)->not->toBeNull();
    Mail::assertNothingOutgoing();
});

it('D-277: a new link only for a user who has still to set password and second factor', function () {
    $agent = User::factory()->agent()->create();

    adminRuns($this, 'users.resend-link', $agent,
        "L'utente ha già configurato l'accesso: usa il reset della password o della verifica in due passaggi");

    Mail::assertNothingOutgoing();
});
