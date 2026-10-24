<?php

use App\Domain\Users\Auth\LoginLockedOut;
use App\Domain\Users\Auth\LoginRateLimiter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Lockout after 5 failed attempts for 15 minutes (M01-CA2, D-214), per e-mail and IP address.
| The lock dispatches LoginLockedOut once: the audit records it (tests/Feature/Audit/AccessAuditTest,
| "l'evento è in audit"), the e-mails to the user and the admin arrive with PR-08.
*/

const LOCKOUT_PASSWORD = 'Una-password-di-prova-2027';

function failLogin(string $email, int $times = 1): void
{
    foreach (range(1, $times) as $ignored) {
        test()->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => 'password-sbagliata']);
    }
}

it('M01-CA2: locks the account for 15 minutes after 5 failed logins', function () {
    Event::fake([LoginLockedOut::class]);
    $user = User::factory()->withoutTwoFactor()->withPassword(LOCKOUT_PASSWORD)->create();

    failLogin($user->email, 4);
    $this->from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password-sbagliata'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.throttle', ['minutes' => '15'])]);
    Event::assertDispatchedTimes(LoginLockedOut::class, 1);

    // Even the right password is refused during the lock.
    $this->from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->travel(14)->minutes();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD]);
    $this->assertGuest();

    $this->travel(2)->minutes();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD]);
    $this->assertAuthenticatedAs($user);
});

it('M01-CA2: shows the time left on the login page and disables the password', function () {
    $user = User::factory()->withPassword(LOCKOUT_PASSWORD)->create();

    failLogin($user->email, 5);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('lockedSeconds', fn (int $seconds): bool => $seconds > 890 && $seconds <= 900));
});

it('M01-CA2: counts only the attempts within 15 minutes', function () {
    $user = User::factory()->withoutTwoFactor()->withPassword(LOCKOUT_PASSWORD)->create();

    failLogin($user->email, 4);
    $this->travel(16)->minutes();
    failLogin($user->email);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD]);
    $this->assertAuthenticatedAs($user);
});

it('M01-CA2: clears the count after a successful login', function () {
    $user = User::factory()->withoutTwoFactor()->withPassword(LOCKOUT_PASSWORD)->create();

    failLogin($user->email, 4);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD]);
    $this->assertAuthenticatedAs($user);

    $request = Request::create('/accesso', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
    expect(app(LoginRateLimiter::class)->attemptsFor($user->email, $request))->toBe(0);
});

it('D-214: locks the e-mail and IP pair, not the other users', function () {
    $locked = User::factory()->withPassword(LOCKOUT_PASSWORD)->create();
    $other = User::factory()->withoutTwoFactor()->withPassword(LOCKOUT_PASSWORD)->create();

    failLogin($locked->email, 5);

    $this->post(route('login.store'), ['email' => $other->email, 'password' => LOCKOUT_PASSWORD]);
    $this->assertAuthenticatedAs($other);
});

it('D-214: answers an unknown e-mail exactly like an existing one', function () {
    failLogin('nessuno@example.test', 4);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => 'nessuno@example.test', 'password' => 'password-sbagliata'])
        ->assertSessionHasErrors(['email' => __('auth.throttle', ['minutes' => '15'])]);
});

it('D-214: counts wrong codes of the second factor towards the same lock', function () {
    $user = User::factory()->withPassword(LOCKOUT_PASSWORD)->create();

    failLogin($user->email, 2);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));

    // Fifth failure: the pending login is dropped and the lock starts.
    $this->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.throttle', ['minutes' => '15'])]);
    expect(session('login.id'))->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('D-214: accepts no code while the account is locked', function () {
    $user = User::factory()->withPassword(LOCKOUT_PASSWORD)->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => LOCKOUT_PASSWORD])
        ->assertRedirect(route('two-factor.login'));

    // Locked meanwhile, e.g. by attempts from another tab.
    failLogin($user->email, 5);

    $this->post(route('two-factor.login.store'), ['code' => totpCode()])
        ->assertRedirect(route('login'));
    $this->assertGuest();
});
