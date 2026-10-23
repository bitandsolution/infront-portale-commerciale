<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use RuntimeException;

/**
 * Fictitious users for the local environment (CLAUDE.md §7, D-270): 2 managers, 3 agents,
 * the third deactivated. Run only with APP_ENV=local (DatabaseSeeder).
 *
 * For the collaudo on the VM (PR-03) the .env of the VM can set:
 * - DEV_USERS_PASSWORD: the password of every test user (at least 12 characters);
 * - DEV_USERS_TOTP_SECRET: one TOTP key (base32) shared by the users marked below, already
 *   confirmed, so a single entry in the authenticator app works for all of them.
 * "agente2" never gets the key, to try the mandatory setup (M01-CA1).
 * Without the variables passwords are random and nobody has the second factor.
 *
 * The first admin is created with `php artisan users:create-admin`, so none is seeded here.
 */
class DevelopmentUsersSeeder extends Seeder
{
    /** @var list<array{string, string, string, Role, bool, bool}> first name, last name, e-mail, role, active, shared TOTP key */
    private const USERS = [
        ['Marta', 'Gestori', 'gestore1@example.test', Role::Manager, true, true],
        ['Paolo', 'Gestori', 'gestore2@example.test', Role::Manager, true, true],
        ['Luca', 'Agenti', 'agente1@example.test', Role::Agent, true, true],
        ['Sara', 'Agenti', 'agente2@example.test', Role::Agent, true, false],
        ['Dario', 'Agenti', 'agente3@example.test', Role::Agent, false, true],
    ];

    public function run(): void
    {
        $password = $this->password();
        $secret = $this->totpSecret();

        foreach (self::USERS as [$firstName, $lastName, $email, $role, $active, $sharedKey]) {
            $twoFactor = $sharedKey && $secret !== null;

            User::query()->updateOrCreate(['email' => $email], [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => $role,
                'password' => Hash::make($password ?? Str::random(32)),
                'is_active' => $active,
                'activated_at' => now(),
                'deactivated_at' => $active ? null : now(),
            ])->forceFill([
                'two_factor_secret' => $twoFactor ? Fortify::currentEncrypter()->encrypt($secret) : null,
                'two_factor_recovery_codes' => $twoFactor ? Fortify::currentEncrypter()->encrypt((string) json_encode(
                    array_map(fn (): string => RecoveryCode::generate(), range(1, 8)),
                )) : null,
                'two_factor_confirmed_at' => $twoFactor ? now() : null,
            ])->save();
        }

        $this->command?->line($password !== null
            ? 'Test users: password from DEV_USERS_PASSWORD.'
            : 'Test users: random passwords (set DEV_USERS_PASSWORD in .env to sign in with them).');
        $this->command?->line($secret !== null
            ? 'Test users: shared two-factor key from DEV_USERS_TOTP_SECRET (agente2 without second factor).'
            : 'Test users: no second factor (set DEV_USERS_TOTP_SECRET in .env to share one key).');
    }

    private function password(): ?string
    {
        $password = config('auth.development_users.password');

        if (! is_string($password) || $password === '') {
            return null;
        }

        if (mb_strlen($password) < 12) {
            throw new RuntimeException('DEV_USERS_PASSWORD must be at least 12 characters long (D-214).');
        }

        return $password;
    }

    private function totpSecret(): ?string
    {
        $secret = config('auth.development_users.totp_secret');

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $secret = strtoupper(str_replace(' ', '', $secret));

        if (preg_match('/^[A-Z2-7]{16,}$/', $secret) !== 1) {
            throw new RuntimeException('DEV_USERS_TOTP_SECRET must be a base32 key of at least 16 characters (letters A-Z, digits 2-7).');
        }

        return $secret;
    }
}
