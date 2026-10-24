<?php

use App\Models\User;

/*
| UserPolicy (M01 §5; D-139): only the admin manages users.
*/

it('D-139: lets only the admin list, create, edit and deactivate users', function (string $role, bool $allowed) {
    $user = User::factory()->state(['role' => $role])->create();
    $other = User::factory()->agent()->create();

    expect($user->can('viewAny', User::class))->toBe($allowed)
        ->and($user->can('create', User::class))->toBe($allowed)
        ->and($user->can('update', $other))->toBe($allowed)
        ->and($user->can('deactivate', $other))->toBe($allowed)
        ->and($user->can('reactivate', $other))->toBe($allowed)
        ->and($user->can('resetAccess', $other))->toBe($allowed)
        ->and($user->can('view', $other))->toBe($allowed);
})->with([
    'agente' => ['agent', false],
    'gestore' => ['manager', false],
    'admin' => ['admin', true],
]);

it('lets every user see his own profile', function (string $role) {
    $user = User::factory()->state(['role' => $role])->create();

    expect($user->can('view', $user))->toBeTrue();
})->with(['agent', 'manager', 'admin']);

it('D-281: does not let the admin deactivate himself or reset his own access from the Users page', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('deactivate', $admin))->toBeFalse()
        ->and($admin->can('reactivate', $admin))->toBeFalse()
        ->and($admin->can('resetAccess', $admin))->toBeFalse()
        ->and($admin->can('update', $admin))->toBeTrue();
});

it('RB-05: a deactivated admin can do nothing', function () {
    $admin = User::factory()->admin()->inactive()->create();

    expect($admin->can('viewAny', User::class))->toBeFalse()
        ->and($admin->can('view', $admin))->toBeFalse();
});
