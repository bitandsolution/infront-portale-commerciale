<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/*
| First-admin command (PR-02): users:create-admin.
*/

const STRONG_PASSWORD = 'Campo-Sportivo-2027!';

beforeEach(function () {
    // Password::uncompromised() asks the breached-password archive; no real request in tests.
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
});

/**
 * @param  array<string, string>  $answers
 */
function runCreateAdmin(object $test, array $answers): mixed
{
    return $test->artisan('users:create-admin')
        ->expectsQuestion('Nome', $answers['first_name'] ?? 'Laura')
        ->expectsQuestion('Cognome', $answers['last_name'] ?? 'Verdi')
        ->expectsQuestion('E-mail', $answers['email'] ?? 'laura.verdi@example.test')
        ->expectsQuestion('Password (almeno 12 caratteri)', $answers['password'] ?? STRONG_PASSWORD)
        ->expectsQuestion('Ripeti la password', $answers['password_confirmation'] ?? $answers['password'] ?? STRONG_PASSWORD);
}

it('creates the first admin, active, with the typed password', function () {
    runCreateAdmin($this, ['email' => ' Laura.Verdi@Example.test '])
        ->expectsOutputToContain('Admin creato: laura.verdi@example.test')
        ->assertSuccessful();

    $admin = User::query()->sole();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->first_name)->toBe('Laura')
        ->and($admin->email)->toBe('laura.verdi@example.test')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->activated_at)->not->toBeNull()
        ->and($admin->two_factor_confirmed_at)->toBeNull()
        ->and(Hash::check(STRONG_PASSWORD, $admin->password))->toBeTrue();
});

it('refuses to run when an admin already exists', function () {
    User::factory()->admin()->create();

    $this->artisan('users:create-admin')
        ->expectsOutputToContain('Esiste già un admin')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('runs only interactively', function () {
    $this->artisan('users:create-admin', ['--no-interaction' => true])
        ->expectsOutputToContain('solo interattivo')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('M01 §9: refuses an e-mail already used, whatever the case', function () {
    User::factory()->agent()->create(['email' => 'laura.verdi@example.test']);

    runCreateAdmin($this, ['email' => 'LAURA.VERDI@example.test'])
        ->expectsOutputToContain('Il valore del campo e-mail è già in uso.')
        ->assertFailed();

    expect(User::query()->where('role', Role::Admin)->exists())->toBeFalse();
});

it('M01 §9: refuses an invalid e-mail', function () {
    runCreateAdmin($this, ['email' => 'laura.verdi'])
        ->expectsOutputToContain('Il campo e-mail deve essere un indirizzo e-mail valido.')
        ->assertFailed();
});

it('D-214: refuses a password shorter than 12 characters', function () {
    runCreateAdmin($this, ['password' => 'Corta-123', 'password_confirmation' => 'Corta-123'])
        ->expectsOutputToContain('Il campo password deve contenere almeno 12 caratteri.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses a confirmation that does not match', function () {
    runCreateAdmin($this, ['password_confirmation' => 'Altra-Password-2027!'])
        ->expectsOutputToContain('Il campo password non coincide con la conferma.')
        ->assertFailed();
});

it('D-214: refuses a password found in the breached-password archive', function () {
    $hash = strtoupper(sha1(STRONG_PASSWORD));
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).':42')]);

    runCreateAdmin($this, [])
        ->expectsOutputToContain('compare in archivi di password violate')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
