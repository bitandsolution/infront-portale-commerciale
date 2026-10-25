<?php

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
| Area Gates (D-208, D-215): the M01 §4 matrix written again by hand, row by row,
| and compared with App\Enums\Ability. [agent, manager, admin]
*/

/**
 * @return array<string, array{bool, bool, bool}>
 */
function expectedAbilities(): array
{
    return [
        'dashboard' => [true, true, true],
        'profile' => [true, true, true],

        // M02 — consultation: manager, admin; agent only through the configurator (D-038)
        'sports.view' => [false, true, true],
        'sports.structure.manage' => [false, false, true],     // seasons, competitions, clubs (D-137)
        'sports.calendars.manage' => [false, true, true],      // calendars, import, matches, stadiums (D-137)

        // M03
        'catalog.view' => [false, true, true],                 // D-137
        'catalog.manage' => [false, false, true],              // D-020

        // M04
        'inventory.view' => [false, true, true],               // the agent sees residuals in the configurator only
        'inventory.capacity.manage' => [false, false, true],   // D-137

        // M05
        'clients.own' => [true, false, true],                  // D-076, D-078
        'clients.view_all' => [false, true, true],             // D-082
        'clients.manage_all' => [false, false, true],          // assign, merge (D-082)
        'commodity_sectors.manage' => [false, false, true],    // D-075

        // M06, M07
        'proposals.own' => [true, false, false],               // D-069, D-100
        'proposals.view_all' => [false, true, true],
        'proposals.review' => [false, true, true],             // D-068, D-098, D-104
        'proposals.force' => [false, false, true],             // D-070
        'proposals.manage_approved' => [false, false, true],   // D-041, D-095

        // M08
        'content.manage' => [false, true, true],               // D-109

        // M09
        'documents.download_all' => [false, true, true],       // D-090
        'documents.regenerate' => [false, false, true],        // D-125

        // M10
        'audit.view' => [false, false, true],                  // D-129
        'reports.view' => [false, true, true],                 // D-134
        'reports.personal' => [true, false, false],            // R-06 (D-130)

        // M01
        'users.manage' => [false, false, true],                // D-139
        'settings.manage' => [false, false, true],

        // Queues panel (Horizon, progettazione-tecnica §5.2.7; PR-08)
        'queues.monitor' => [false, false, true],
    ];
}

it('D-215: covers every area permission of the code', function () {
    expect(array_keys(expectedAbilities()))
        ->toEqualCanonicalizing(array_map(fn (Ability $a): string => $a->value, Ability::cases()));
});

it('D-208: grants each area permission only to the roles of the M01 §4 matrix', function (string $ability, array $expected) {
    $users = [
        User::factory()->agent()->make(),
        User::factory()->manager()->make(),
        User::factory()->admin()->make(),
    ];

    foreach ($users as $index => $user) {
        expect(Gate::forUser($user)->allows($ability))
            ->toBe($expected[$index], "{$ability} for {$user->role->value}");
    }
})->with(fn (): array => collect(expectedAbilities())
    ->map(fn (array $expected, string $ability): array => [$ability, $expected])
    ->all());

it('RB-05: denies every permission to a deactivated user, whatever the role', function (Role $role) {
    $user = User::factory()->state(['role' => $role])->inactive()->make();

    foreach (Ability::cases() as $ability) {
        expect(Gate::forUser($user)->allows($ability->value))->toBeFalse($ability->value);
    }
})->with([Role::Agent, Role::Manager, Role::Admin]);

it('M01-CA3: denies the manager price lists, capacities, seasons, competitions, clubs, users and audit', function () {
    $manager = User::factory()->manager()->make();

    foreach ([
        Ability::CatalogManage,
        Ability::InventoryCapacityManage,
        Ability::SportsStructureManage,
        Ability::UsersManage,
        Ability::AuditView,
    ] as $ability) {
        expect(Gate::forUser($manager)->allows($ability->value))->toBeFalse($ability->value);
    }
});
