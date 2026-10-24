<?php

use App\Domain\Users\Actions\CreateUser;
use App\Domain\Users\Data\NewUserData;
use App\Domain\Users\Services\AccessLinks;
use App\Enums\Role;
use App\Mail\AccessLinkMail;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

/*
| Activation from the link (M01 §5; D-209, D-277, D-278): password and second factor on one page,
| saved together when the code is right; 48 hours; once.
*/

const ACTIVATION_PASSWORD = 'Curva-Nord-Lazio-2027!';

beforeEach(function () {
    Mail::fake();
    fakePasswordArchive();
});

/**
 * New user created by the admin, waiting for activation, and the URL of his link.
 *
 * @return array{User, string}
 */
function pendingUser(string $email = 'nuovo.agente@example.test'): array
{
    $user = app(CreateUser::class)->handle(new NewUserData(
        firstName: 'Nuovo',
        lastName: 'Agente',
        email: $email,
        role: Role::Agent,
    ));

    return [$user, sentAccessLink($email)];
}

/**
 * Opens the activation page and returns the TOTP key it shows.
 */
function activationSecret(object $test, string $url): string
{
    $secret = '';

    // A full closure (not fn): arrow functions capture $secret by value.
    $test->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$secret): void {
        $page->component('Auth/Activate')
            ->where('secret', function (string $value) use (&$secret): bool {
                $secret = str_replace(' ', '', $value);

                return $secret !== '';
            });
    });

    return $secret;
}

/**
 * @return array<string, string>
 */
function activationForm(string $code, string $password = ACTIVATION_PASSWORD): array
{
    return ['password' => $password, 'password_confirmation' => $password, 'code' => $code];
}

it('M01 §5: a user waiting for activation cannot sign in', function () {
    [$user] = pendingUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => ACTIVATION_PASSWORD])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('D-278: sets password and second factor together, then shows the recovery codes and opens the portal', function () {
    [$user, $url] = pendingUser();

    $secret = activationSecret($this, $url);

    $this->post($url, activationForm(app(Google2FA::class)->getCurrentOtp($secret)))
        ->assertRedirect(route('two-factor.recovery-codes'));

    $user->refresh();

    expect(Hash::check(ACTIVATION_PASSWORD, $user->password))->toBeTrue()
        ->and($user->activated_at)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->recoveryCodes())->toHaveCount(8);
    $this->assertAuthenticatedAs($user);

    $this->get(route('two-factor.recovery-codes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/RecoveryCodes')->has('codes', 8));
    $this->get(PortalPages::url('dashboard'))->assertOk();
});

it('D-278: a wrong code saves nothing and the link stays valid', function () {
    [$user, $url] = pendingUser();
    $password = $user->password;

    $secret = activationSecret($this, $url);

    $this->post($url, activationForm('000000'))
        ->assertSessionHasErrors(['code' => __('auth.two_factor.invalid_code')]);

    $user->refresh();

    expect($user->password)->toBe($password)
        ->and($user->activated_at)->toBeNull()
        ->and($user->two_factor_secret)->toBeNull();
    $this->assertGuest();

    // Same page and same key: the user corrects the code and goes on.
    expect(activationSecret($this, $url))->toBe($secret);
});

it('D-278: leaving halfway changes nothing and the same link shows the same key', function () {
    [$user, $url] = pendingUser();

    $secret = activationSecret($this, $url);
    $again = activationSecret($this, $url);

    expect($again)->toBe($secret)
        ->and($user->fresh()?->two_factor_secret)->toBeNull()
        ->and($user->fresh()?->activated_at)->toBeNull();
});

it('D-214: refuses a password shorter than 12 characters or not confirmed', function () {
    [$user, $url] = pendingUser();
    $secret = activationSecret($this, $url);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post($url, ['password' => 'Corta-2027', 'password_confirmation' => 'Corta-2027', 'code' => $code])
        ->assertSessionHasErrors('password');
    $this->post($url, ['password' => ACTIVATION_PASSWORD, 'password_confirmation' => 'Diversa-Password-2027', 'code' => $code])
        ->assertSessionHasErrors('password');

    expect($user->fresh()?->activated_at)->toBeNull();
});

it('D-209: the link expires after 48 hours', function () {
    [$user, $url] = pendingUser();

    $this->travel(AccessLinks::LIFETIME_HOURS)->hours();
    $this->travel(1)->minutes();

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
    $this->post($url, activationForm('123456'))->assertRedirect($url);

    expect($user->fresh()?->activated_at)->toBeNull();
});

it('D-277: the link works once', function () {
    [$user, $url] = pendingUser();
    $secret = activationSecret($this, $url);

    $this->post($url, activationForm(app(Google2FA::class)->getCurrentOtp($secret)))->assertRedirect();
    $this->post(route('logout'));

    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
    expect($this->app['db']->table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

it('D-277: a new link replaces the previous one', function () {
    $admin = User::factory()->admin()->create();
    [$user, $oldUrl] = pendingUser();

    $this->actingAs($admin)->post(route('users.resend-link', $user))
        ->assertSessionHas('toast.message', 'Nuovo link di accesso inviato a '.$user->email);
    $newUrl = sentAccessLink($user->email);
    $this->post(route('logout'));

    expect($newUrl)->not->toBe($oldUrl);
    $this->get($oldUrl)->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
    $this->get($newUrl)->assertInertia(fn (Assert $page) => $page->component('Auth/Activate')->where('renewal', false));
});

it('D-277: an unknown token or the link of a deactivated user is not valid, with the same page', function () {
    [$user, $url] = pendingUser();
    $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
    $this->get(route('access.activation', ['token' => str_repeat('a', 64)]))
        ->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
});

it('D-277: the e-mail tells the user what to do and how long the link lasts', function () {
    [$user, $url] = pendingUser();

    Mail::assertSent(AccessLinkMail::class, function (AccessLinkMail $mail) use ($user, $url): bool {
        $mail->assertHasSubject('Attiva il tuo account del Portale Commerciale Infront');
        $mail->assertSeeInHtml('Ciao Nuovo,');
        $mail->assertSeeInHtml('48 ore');
        $mail->assertSeeInHtml($url, false);

        return $mail->hasTo($user->email);
    });
});
