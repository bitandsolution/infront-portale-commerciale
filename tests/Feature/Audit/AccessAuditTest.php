<?php

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;

/*
| Access events of the audit (D-128, D-274, D-283; M10-CA2, M01-CA2): login completed or failed,
| logout, lockout, second factor, end of the session. Each with date, user and IP address.
| The login page answers as before: the audit never changes what the user sees (D-214).
*/

const ACCESS_PASSWORD = 'Una-password-di-prova-2027';

function accessUser(): User
{
    return User::factory()->agent()->withPassword(ACCESS_PASSWORD)->create([
        'first_name' => 'Luca',
        'last_name' => 'Rossi',
        'email' => 'luca.rossi@example.test',
    ]);
}

function wrongLogin(object $test, string $email, int $times = 1): void
{
    foreach (range(1, $times) as $ignored) {
        $test->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => 'password-sbagliata']);
    }
}

it('M10-CA2: a failed login is recorded with date, attempted user and IP', function () {
    $user = accessUser();
    $this->travelTo(now()->setTime(9, 30));

    wrongLogin($this, ' Luca.Rossi@Example.test ');

    $event = soleAuditEvent(AuditAction::LoginFailed);
    expect($event)
        ->user_id->toBe($user->id)
        ->user_name->toBe('Luca Rossi')
        ->user_role->toBe('agent')
        ->area->toBe('access')
        ->object_id->toBe($user->id)
        ->reason->toBe('invalid_credentials')
        ->ip_address->toBe('127.0.0.1')
        ->and($event->occurred_at->format('H:i'))->toBe('09:30')
        ->and((string) json_encode($event->getAttributes()))->not->toContain('password-sbagliata');
});

it('M10-CA2: a failed login with an unknown e-mail records what was typed, the page answering as always', function () {
    $this->from(route('login'))
        ->post(route('login.store'), ['email' => 'Nessuno@Example.test', 'password' => 'password-sbagliata'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    expect(soleAuditEvent(AuditAction::LoginFailed))
        ->user_id->toBeNull()
        ->user_name->toBeNull()
        ->object_id->toBeNull()
        ->object_label->toBe('nessuno@example.test')
        ->reason->toBe('unknown_email')
        ->ip_address->toBe('127.0.0.1');
});

it('M10-CA2: records the failed logins of a user not activated yet and of a deactivated user', function () {
    $pending = User::factory()->withoutTwoFactor()->withPassword(ACCESS_PASSWORD)->create(['activated_at' => null]);
    $inactive = User::factory()->inactive()->withPassword(ACCESS_PASSWORD)->create();

    $this->post(route('login.store'), ['email' => $pending->email, 'password' => ACCESS_PASSWORD]);
    $this->from(route('login'))->post(route('login.store'), ['email' => $inactive->email, 'password' => ACCESS_PASSWORD])
        ->assertSessionHasErrors(['email' => __('ui.auth.deactivated')]);

    expect(auditEventsOf(AuditAction::LoginFailed)->map(fn (AuditEvent $event): array => [$event->user_id, $event->reason])->all())
        ->toBe([[$pending->id, 'not_activated'], [$inactive->id, 'deactivated']]);
});

it('D-128: a login is recorded once, after the second factor', function () {
    $user = accessUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => ACCESS_PASSWORD])
        ->assertRedirect(route('two-factor.login'));
    expect(auditEventsOf(AuditAction::LoginSucceeded))->toBeEmpty();

    $this->post(route('two-factor.login.store'), ['code' => totpCode()]);
    $this->assertAuthenticatedAs($user);

    expect(soleAuditEvent(AuditAction::LoginSucceeded))
        ->user_id->toBe($user->id)
        ->ip_address->toBe('127.0.0.1');
});

it('D-128: records a wrong code of the second factor and a login with a recovery code', function () {
    $user = accessUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => ACCESS_PASSWORD]);
    $this->post(route('two-factor.login.store'), ['code' => '000000']);
    $this->post(route('two-factor.login.store'), ['recovery_code' => $user->recoveryCodes()[0]]);
    $this->assertAuthenticatedAs($user);

    expect(soleAuditEvent(AuditAction::TwoFactorFailed))->user_id->toBe($user->id)->ip_address->toBe('127.0.0.1')
        ->and(soleAuditEvent(AuditAction::RecoveryCodeUsed))->user_id->toBe($user->id)
        ->and(soleAuditEvent(AuditAction::LoginSucceeded))->user_id->toBe($user->id);
    expect(AuditEvent::query()->get()->implode(fn (AuditEvent $event): string => (string) json_encode($event->getAttributes())))
        ->not->toContain($user->recoveryCodes()[1]);
});

it('M01-CA2: after 5 failed logins the lockout is in the audit, once', function () {
    $user = accessUser();

    wrongLogin($this, $user->email, 5);

    expect(auditEventsOf(AuditAction::LoginFailed))->toHaveCount(5)
        ->and(soleAuditEvent(AuditAction::LockedOut))
        ->user_id->toBe($user->id)
        ->object_id->toBe($user->id)
        ->ip_address->toBe('127.0.0.1');

    // Attempts during the lock are failed logins with reason "locked", not new lockouts.
    $this->post(route('login.store'), ['email' => $user->email, 'password' => ACCESS_PASSWORD]);

    expect(auditEventsOf(AuditAction::LockedOut))->toHaveCount(1)
        ->and(auditEventsOf(AuditAction::LoginFailed)->last()?->reason)->toBe('locked');
});

it('M01-CA2: also the lockout of an unknown e-mail is recorded, with what was typed', function () {
    wrongLogin($this, 'nessuno@example.test', 5);

    expect(soleAuditEvent(AuditAction::LockedOut))
        ->user_id->toBeNull()
        ->object_label->toBe('nessuno@example.test');
});

it('M01-CA2: the lockout started by wrong codes of the second factor is in the audit', function () {
    $user = accessUser();

    wrongLogin($this, $user->email, 2);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => ACCESS_PASSWORD]);
    foreach (range(1, 3) as $ignored) {
        $this->post(route('two-factor.login.store'), ['code' => '000000']);
    }

    expect(auditEventsOf(AuditAction::TwoFactorFailed))->toHaveCount(3)
        ->and(soleAuditEvent(AuditAction::LockedOut))->user_id->toBe($user->id);
});

it('D-128: records the logout from the "Esci" button', function () {
    $user = accessUser();

    $this->actingAs($user)->post(route('logout'))->assertRedirect();

    expect(soleAuditEvent(AuditAction::Logout))
        ->user_id->toBe($user->id)
        ->ip_address->toBe('127.0.0.1');
});

it('D-283: records the sessions closed by the portal with their reason, not as a logout', function () {
    $agent = User::factory()->agent()->create();
    $manager = User::factory()->manager()->create();
    $admin = User::factory()->admin()->create();

    // Inactivity beyond the limit of the role (D-214).
    $this->actingAs($manager)->get(route('pages.dashboard'))->assertOk();
    $this->travel(121)->minutes();
    $this->get(route('pages.dashboard'))->assertRedirect(route('login'));

    // Deactivated while signed in (RB-05).
    $this->actingAs($agent)->get(route('pages.dashboard'))->assertOk();
    $agent->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
    $this->get(route('pages.dashboard'))->assertRedirect(route('login'));

    // Second factor reset from the server meanwhile (D-280).
    $this->actingAs($admin)->get(route('pages.dashboard'))->assertOk();
    $admin->forceFill(['two_factor_confirmed_at' => null, 'two_factor_secret' => null])->save();
    $this->get(route('pages.dashboard'))->assertRedirect(route('login'));

    expect(soleAuditEvent(AuditAction::SessionExpired))->user_id->toBe($manager->id)->reason->toBe('idle')
        ->and(auditEventsOf(AuditAction::SessionClosed)->map(fn (AuditEvent $event): array => [$event->user_id, $event->reason])->all())
        ->toBe([[$agent->id, 'deactivated'], [$admin->id, 'two_factor_reset']])
        ->and(auditEventsOf(AuditAction::Logout))->toBeEmpty();
});
