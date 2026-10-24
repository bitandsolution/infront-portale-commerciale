<?php

use App\Domain\Users\Actions\ActivateAccount;
use App\Domain\Users\Actions\ChangePassword;
use App\Domain\Users\Actions\CompletePasswordReset;
use App\Domain\Users\Actions\CreateUser;
use App\Domain\Users\Actions\DeactivateUser;
use App\Domain\Users\Actions\ReactivateUser;
use App\Domain\Users\Actions\ResendAccessLink;
use App\Domain\Users\Actions\ResetTwoFactor;
use App\Domain\Users\Actions\StartPasswordReset;
use App\Domain\Users\Actions\UpdateProfile;
use App\Domain\Users\Actions\UpdateUser;
use App\Domain\Users\Data\NewUserData;
use App\Domain\Users\Data\UserDetailsData;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Domain\Users\Services\AccessLinks;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/*
| Audit of the user management (M10-CA1 for the area Utenti of M10 §4.1; D-127, D-283): every
| Action writes its event in its own transaction, with author, date, object and, for the changes,
| only the changed fields before and after. Never passwords, hashes, tokens or second factor.
| An Action that fails leaves no event; an event that cannot be written cancels the Action.
*/

const AUDIT_PASSWORD = 'Una-password-di-prova-2027';

beforeEach(function () {
    Mail::fake();
    fakePasswordArchive();
});

/**
 * Every value written in an event, as one string: to check that no secret ended up there.
 */
function auditDump(AuditEvent $event): string
{
    return (string) json_encode($event->getAttributes());
}

it('M10-CA1: creation of a user from the Users page, with author, date, object and data', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);

    $this->actingAs($admin)->post(route('users.store'), [
        'first_name' => 'Giulia',
        'last_name' => 'Neri',
        'email' => 'giulia.neri@example.test',
        'phone' => '',
        'role' => 'agent',
    ])->assertRedirect();

    $user = User::query()->where('email', 'giulia.neri@example.test')->sole();
    $event = soleAuditEvent(AuditAction::UserCreated);

    expect($event)
        ->user_id->toBe($admin->id)
        ->user_name->toBe('Anna Bianchi')
        ->user_role->toBe('admin')
        ->area->toBe('users')
        ->object_type->toBe('user')
        ->object_id->toBe($user->id)
        ->object_label->toBe('Giulia Neri (giulia.neri@example.test)')
        ->old_values->toBeNull()
        ->new_values->toBe([
            'first_name' => 'Giulia',
            'last_name' => 'Neri',
            'email' => 'giulia.neri@example.test',
            'phone' => null,
            'role' => 'agent',
            'is_active' => true,
        ])
        ->ip_address->toBeNull()
        ->and($event->occurred_at->isToday())->toBeTrue()
        ->and(auditDump($event))->not->toContain($user->password);
});

it('M10-CA1: the first admin created from the server has the system as author', function () {
    app(CreateUser::class)->handle(new NewUserData(
        firstName: 'Laura',
        lastName: 'Verdi',
        email: 'laura.verdi@example.test',
        role: Role::Admin,
        password: AUDIT_PASSWORD,
    ));

    $event = soleAuditEvent(AuditAction::UserCreated);

    expect($event)
        ->user_id->toBeNull()
        ->user_name->toBe(AuditEvent::SYSTEM)
        ->and($event->new_values)->toMatchArray(['role' => 'admin'])
        ->and(auditDump($event))->not->toContain(AUDIT_PASSWORD);
});

it('M10-CA1: change of role, with only the changed fields before and after', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Rossi', 'phone' => null]);

    $this->actingAs($admin)->put(route('users.update', $agent), [
        'first_name' => 'Luca',
        'last_name' => 'Rossi',
        'email' => $agent->email,
        'phone' => '333 1234567',
        'role' => 'manager',
    ])->assertRedirect();

    $event = soleAuditEvent(AuditAction::UserRoleChanged);

    expect($event)
        ->user_id->toBe($admin->id)
        ->object_id->toBe($agent->id)
        ->old_values->toBe(['phone' => null, 'role' => 'agent'])
        ->new_values->toBe(['phone' => '333 1234567', 'role' => 'manager'])
        ->and(auditEventsOf(AuditAction::UserUpdated))->toBeEmpty();
});

it('M10-CA1: change of data without the role, and no event when nothing changes', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create(['email' => 'vecchia@example.test']);
    $data = new UserDetailsData($agent->first_name, $agent->last_name, 'nuova@example.test', Role::Agent, $agent->phone);

    app(UpdateUser::class)->handle($agent, $data, $admin);
    app(UpdateUser::class)->handle($agent->fresh() ?? $agent, $data, $admin);

    $event = soleAuditEvent(AuditAction::UserUpdated);
    expect($event->old_values)->toBe(['email' => 'vecchia@example.test'])
        ->and($event->new_values)->toBe(['email' => 'nuova@example.test']);
});

it('M10-CA1: deactivation and reactivation', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    app(DeactivateUser::class)->handle($agent, $admin);
    app(ReactivateUser::class)->handle($agent, $admin);

    expect(soleAuditEvent(AuditAction::UserDeactivated))
        ->user_id->toBe($admin->id)
        ->object_id->toBe($agent->id)
        ->old_values->toBe(['is_active' => true])
        ->new_values->toBe(['is_active' => false]);
    expect(soleAuditEvent(AuditAction::UserReactivated))
        ->old_values->toBe(['is_active' => false])
        ->new_values->toBe(['is_active' => true]);
});

it('M10-CA1: password reset and second factor reset by the admin, without secrets', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $manager = User::factory()->manager()->create();
    $oldSecret = (string) $manager->two_factor_secret;

    app(StartPasswordReset::class)->handle($agent, $admin);
    app(ResetTwoFactor::class)->handle($manager, $admin);

    $reset = soleAuditEvent(AuditAction::UserPasswordResetRequested);
    $twoFactor = soleAuditEvent(AuditAction::UserTwoFactorReset);

    expect($reset)->user_id->toBe($admin->id)->object_id->toBe($agent->id)->old_values->toBeNull()->new_values->toBeNull()
        ->and($twoFactor)->user_id->toBe($admin->id)->object_id->toBe($manager->id)->new_values->toBeNull()
        ->and(auditDump($twoFactor))->not->toContain($oldSecret);
});

it('M10-CA1: new access link, with its type and never the token', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->agent()->withoutTwoFactor()->create(['activated_at' => null]);

    app(ResendAccessLink::class)->handle($pending, $admin);

    $event = soleAuditEvent(AuditAction::UserAccessLinkSent);
    expect($event->new_values)->toBe(['access_link' => 'activation'])
        ->and(auditDump($event))->not->toContain(basename(sentAccessLink($pending->email)));
});

it('M10-CA1: activation from the link and second factor set again after a reset', function () {
    $google2fa = app(Google2FA::class);

    $pending = User::factory()->agent()->withoutTwoFactor()->create(['activated_at' => null]);
    $secret = $google2fa->generateSecretKey();
    app(ActivateAccount::class)->handle(app(AccessLinks::class)->create($pending), AUDIT_PASSWORD, $secret, totpCode($secret));

    $reset = User::factory()->agent()->withoutTwoFactor()->create();
    $secret2 = $google2fa->generateSecretKey();
    app(ActivateAccount::class)->handle(app(AccessLinks::class)->create($reset), AUDIT_PASSWORD, $secret2, totpCode($secret2));

    expect(soleAuditEvent(AuditAction::UserActivated))
        ->user_id->toBe($pending->id)
        ->object_id->toBe($pending->id)
        ->and(soleAuditEvent(AuditAction::UserTwoFactorConfigured))
        ->user_id->toBe($reset->id)
        ->and(auditDump(soleAuditEvent(AuditAction::UserActivated)))->not->toContain($secret);
});

it('M10-CA1: new password from the reset link, profile and change of password by the user', function () {
    $agent = User::factory()->agent()->create(['first_name' => 'Luca', 'phone' => null]);

    app(CompletePasswordReset::class)->handle(app(AccessLinks::class)->create($agent), AUDIT_PASSWORD, totpCode(UserFactory::TOTP_SECRET));
    app(UpdateProfile::class)->handle($agent, 'Luca', $agent->last_name, '333 0000000');
    app(UpdateProfile::class)->handle($agent, 'Luca', $agent->last_name, '333 0000000');
    app(ChangePassword::class)->handle($agent, 'Un-altra-password-2027');

    expect(soleAuditEvent(AuditAction::UserPasswordReset))->user_id->toBe($agent->id)->new_values->toBeNull()
        ->and(soleAuditEvent(AuditAction::UserProfileUpdated))
        ->user_id->toBe($agent->id)
        ->old_values->toBe(['phone' => null])
        ->new_values->toBe(['phone' => '333 0000000'])
        ->and(soleAuditEvent(AuditAction::UserPasswordChanged))->user_id->toBe($agent->id)->new_values->toBeNull();

    expect(AuditEvent::query()->get()->map(fn (AuditEvent $event): string => auditDump($event))->implode(' '))
        ->not->toContain(AUDIT_PASSWORD)
        ->not->toContain('Un-altra-password-2027')
        ->not->toContain((string) $agent->fresh()?->password);
});

it('M10-CA1: second factor configured at the first login', function () {
    $agent = User::factory()->agent()->withoutTwoFactor()->create();

    $this->actingAs($agent)->get(route('two-factor.setup'))->assertOk();
    $secret = Fortify::currentEncrypter()->decrypt((string) $agent->fresh()?->two_factor_secret);

    $this->post(route('two-factor.confirm'), ['code' => totpCode(is_string($secret) ? $secret : '')])->assertRedirect(route('two-factor.recovery-codes'));

    expect(soleAuditEvent(AuditAction::UserTwoFactorConfigured))->user_id->toBe($agent->id)->object_id->toBe($agent->id);
});

it('D-276: the reset of the second factor from the server has the system as author', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);

    $this->artisan('users:reset-two-factor', ['email' => 'admin@example.test'])
        ->expectsConfirmation("Azzerare la verifica in due passaggi? L'utente la riconfigurerà al prossimo accesso e le sue sessioni aperte verranno chiuse.", 'yes')
        ->assertSuccessful();

    expect(soleAuditEvent(AuditAction::UserTwoFactorReset))
        ->user_id->toBeNull()
        ->user_name->toBe(AuditEvent::SYSTEM)
        ->object_id->toBe($admin->id);
});

it('D-283: an Action refused by its rules leaves no event', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => app(DeactivateUser::class)->handle($admin, $admin))->toThrow(UserActionNotAllowed::class);
    expect(fn () => app(UpdateUser::class)->handle($admin, new UserDetailsData($admin->first_name, $admin->last_name, $admin->email, Role::Agent), $admin))
        ->toThrow(UserActionNotAllowed::class);

    expect(AuditEvent::query()->count())->toBe(0);
});

it('D-283: an Action that fails after its write leaves neither the change nor the event', function () {
    $admin = User::factory()->admin()->create();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Mail server unreachable'));

    expect(fn () => app(CreateUser::class)->handle(new NewUserData('Giulia', 'Neri', 'giulia.neri@example.test', Role::Agent), $admin))
        ->toThrow(RuntimeException::class);

    expect(User::query()->where('email', 'giulia.neri@example.test')->exists())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-283: an event that cannot be written cancels the Action (fail-closed)', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    AuditEvent::creating(fn (): bool => throw new RuntimeException('audit_events unavailable'));

    expect(fn () => app(DeactivateUser::class)->handle($agent, $admin))->toThrow(RuntimeException::class, 'audit_events unavailable');

    expect($agent->fresh()?->is_active)->toBeTrue();
});
