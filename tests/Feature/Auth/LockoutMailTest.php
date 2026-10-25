<?php

use App\Enums\NotificationType;
use App\Mail\LoginLockedAdminMail;
use App\Mail\LoginLockedMail;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
| E-mails of the lock after 5 failed attempts (M01 §7; D-274, D-285; PR-08), sent once per lock by
| the listener of LoginLockedOut: to the user (when and how long, no IP address) and to every other
| active admin (who, when, from which IP address). Nothing for unknown, deactivated or not yet
| activated accounts.
*/

beforeEach(function () {
    Mail::fake();
});

function lockoutMailFail(string $email, int $times, string $ip = '10.0.0.7'): void
{
    foreach (range(1, $times) as $ignored) {
        test()->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('login'))
            ->post(route('login.store'), ['email' => $email, 'password' => 'password-sbagliata']);
    }
}

it('M01 §7: the lock sends one e-mail to the user and one to the admin', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Anna']);
    $agent = User::factory()->agent()->create(['first_name' => 'Marco', 'last_name' => 'Bianchi']);

    lockoutMailFail($agent->email, 5);

    Mail::assertQueued(LoginLockedMail::class, function (LoginLockedMail $mail) use ($agent): bool {
        $mail->assertHasSubject('Accesso al Portale Commerciale Infront bloccato per 15 minuti');
        $mail->assertSeeInHtml('Ciao Marco,');
        $mail->assertSeeInHtml('5 tentativi di accesso non riusciti');
        $mail->assertSeeInHtml('Password dimenticata?', false);
        $mail->assertDontSeeInHtml('10.0.0.7');

        return $mail->hasTo($agent->email);
    });
    Mail::assertQueued(LoginLockedAdminMail::class, function (LoginLockedAdminMail $mail) use ($admin, $agent): bool {
        $mail->assertHasSubject('Accesso bloccato per Marco Bianchi');
        $mail->assertSeeInHtml('Ciao Anna,');
        $mail->assertSeeInHtml($agent->email);
        $mail->assertSeeInHtml('10.0.0.7');
        $mail->assertSeeInHtml(route('users.show', ['user' => $agent->id]), false);

        return $mail->hasTo($admin->email);
    });
    Mail::assertQueuedCount(2);

    expect(NotificationLog::query()->orderBy('id')->get()->map(fn (NotificationLog $log): array => [$log->type, $log->user_id])->all())
        ->toBe([[NotificationType::LoginLocked, $agent->id], [NotificationType::LoginLockedAdmin, $admin->id]]);
});

it('M01 §7: the attempts made during the lock send nothing more', function () {
    User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    lockoutMailFail($agent->email, 9);

    Mail::assertQueuedCount(2);
});

it('M01 §7: a new lock after the end of the previous one sends the e-mails again', function () {
    User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    lockoutMailFail($agent->email, 5);
    $this->travel(16)->minutes();
    lockoutMailFail($agent->email, 5);

    Mail::assertQueuedCount(4);
});

it('M01 §7: no e-mail for an unknown address, a deactivated or a not yet activated user', function () {
    User::factory()->admin()->create();
    $deactivated = User::factory()->agent()->inactive()->create();
    $pending = User::factory()->agent()->withoutTwoFactor()->create(['activated_at' => null]);

    lockoutMailFail('sconosciuto@example.test', 5);
    lockoutMailFail($deactivated->email, 5);
    lockoutMailFail($pending->email, 5);

    Mail::assertNothingOutgoing();
});

it('M01 §7: an admin who locks himself receives only the user e-mail; the other admins are warned', function () {
    $admin = User::factory()->admin()->create();

    lockoutMailFail($admin->email, 5);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(LoginLockedMail::class, fn (LoginLockedMail $mail): bool => $mail->hasTo($admin->email));

    $other = User::factory()->admin()->create();
    $this->travel(16)->minutes();
    lockoutMailFail($admin->email, 5);

    Mail::assertQueued(LoginLockedAdminMail::class, fn (LoginLockedAdminMail $mail): bool => $mail->hasTo($other->email)
        && ! $mail->hasTo($admin->email));
    Mail::assertQueuedCount(3);
});

it('M01 §7: a deactivated admin is not warned', function () {
    User::factory()->admin()->create();
    $deactivatedAdmin = User::factory()->admin()->inactive()->create();
    $agent = User::factory()->agent()->create();

    lockoutMailFail($agent->email, 5);

    Mail::assertNotQueued(LoginLockedAdminMail::class, fn (LoginLockedAdminMail $mail): bool => $mail->hasTo($deactivatedAdmin->email));
    Mail::assertQueuedCount(2);
});

it('M01-CA2: an e-mail that cannot be queued does not change the answer of the login page', function () {
    $agent = User::factory()->agent()->create();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Queue unreachable'));

    lockoutMailFail($agent->email, 4);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
        ->from(route('login'))
        ->post(route('login.store'), ['email' => $agent->email, 'password' => 'password-sbagliata'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.throttle', ['minutes' => '15'])]);
    $this->assertGuest();
});
