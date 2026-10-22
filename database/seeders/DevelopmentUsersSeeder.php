<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fictitious users for the local environment (CLAUDE.md §7): one per role, plus a deactivated agent.
 *
 * No password can be used before PR-03: the local check goes through the development access (D-268).
 * The first admin is created with `php artisan users:create-admin`, so none is seeded here.
 */
class DevelopmentUsersSeeder extends Seeder
{
    /** @var list<array{string, string, string, Role, bool}> first name, last name, e-mail, role, active */
    private const USERS = [
        ['Marta', 'Gestori', 'gestore1@example.test', Role::Manager, true],
        ['Paolo', 'Gestori', 'gestore2@example.test', Role::Manager, true],
        ['Luca', 'Agenti', 'agente1@example.test', Role::Agent, true],
        ['Sara', 'Agenti', 'agente2@example.test', Role::Agent, true],
        ['Dario', 'Agenti', 'agente3@example.test', Role::Agent, false],
    ];

    public function run(): void
    {
        foreach (self::USERS as [$firstName, $lastName, $email, $role, $active]) {
            User::query()->updateOrCreate(['email' => $email], [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => $role,
                'password' => Hash::make(Str::random(32)),
                'is_active' => $active,
                'activated_at' => now(),
                'deactivated_at' => $active ? null : now(),
            ]);
        }
    }
}
