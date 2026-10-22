<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Users table (D-209; progettazione-tecnica §5.3.6).
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function insertUserRow(array $overrides = []): int
{
    return DB::table('users')->insertGetId([
        'first_name' => 'Prova',
        'last_name' => 'Utente',
        'email' => 'prova'.uniqid().'@example.test',
        'role' => 'agent',
        'password' => 'hash',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

it('D-209: has the columns of the users model', function () {
    expect(Schema::hasColumns('users', [
        'id', 'first_name', 'last_name', 'email', 'phone', 'role', 'password',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'is_active', 'activated_at', 'deactivated_at', 'deactivated_by', 'last_login_at',
        'remember_token', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('users', 'name'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse();
});

it('C8: stores the role as VARCHAR(32)', function () {
    $column = collect(Schema::getColumns('users'))->firstWhere('name', 'role');

    expect($column['type'])->toBe('varchar(32)')
        ->and($column['nullable'])->toBeFalse();
});

it('RB-01: a user has exactly one role', function () {
    $user = User::factory()->manager()->create();

    expect($user->fresh()?->role)->toBe(Role::Manager);

    // No role, or a value outside the fixed roles, is refused by the database.
    expect(fn () => insertUserRow(['role' => null]))->toThrow(QueryException::class);
    expect(fn () => insertUserRow(['role' => 'superuser']))->toThrow(QueryException::class);
    expect(fn () => insertUserRow(['role' => 'admin,agent']))->toThrow(QueryException::class);
});

it('M01 §9: refuses a duplicate e-mail, whatever the case', function () {
    User::factory()->create(['email' => 'mario.rossi@example.test']);

    expect(fn () => insertUserRow(['email' => 'Mario.Rossi@Example.test']))->toThrow(QueryException::class);
});

it('stores the e-mail in lower case', function () {
    $user = User::factory()->create(['email' => '  Anna.Bianchi@Example.TEST ']);

    expect($user->fresh()?->email)->toBe('anna.bianchi@example.test');
});

it('RB-05: an inactive user always has the deactivation date, an active one never', function () {
    expect(fn () => insertUserRow(['is_active' => false, 'deactivated_at' => null]))->toThrow(QueryException::class);
    expect(fn () => insertUserRow(['is_active' => true, 'deactivated_at' => now()]))->toThrow(QueryException::class);

    expect(insertUserRow(['is_active' => false, 'deactivated_at' => now()]))->toBeInt();
});

it('C3: refuses to delete a user who deactivated someone (ON DELETE RESTRICT)', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->inactive()->create(['deactivated_by' => $admin->id]);

    expect(fn () => DB::table('users')->where('id', $admin->id)->delete())->toThrow(QueryException::class);
});

it('keeps the two-factor secrets and the password out of serialisation', function () {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
});

it('builds the name and the initials shown in the top bar', function () {
    $user = User::factory()->make(['first_name' => 'Giulia', 'last_name' => 'Esposito']);

    expect($user->fullName())->toBe('Giulia Esposito')
        ->and($user->initials())->toBe('GE');
});
