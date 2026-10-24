<?php

use App\Domain\Users\Actions\DeactivateUser;
use App\Domain\Users\Actions\UpdateUser;
use App\Domain\Users\Data\UserDetailsData;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| "At least one active admin" with two concurrent operations (RB-04, M01-CA5; D-209;
| progettazione-tecnica §5.3.6; CLAUDE.md §11: concurrency tests).
|
| A second database session plays the other admin: it opens its transaction, locks the rows of the
| active admins as ActiveAdminGuard does and keeps the lock. The operation of the first session must
| wait for it (here: a lock wait of 1 second, then the error) and, once the other one has committed,
| must see its effect and refuse to remove the last admin.
*/

const OTHER_SESSION = 'concurrent';

beforeEach(function () {
    $default = config('database.default');
    config(['database.connections.'.OTHER_SESSION => config('database.connections.'.(is_string($default) ? $default : 'mariadb'))]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
});

afterEach(function () {
    $other = DB::connection(OTHER_SESSION);
    while ($other->transactionLevel() > 0) {
        $other->rollBack();
    }
    DB::purge(OTHER_SESSION);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    // Rows written here are committed: clean up for the tests that follow.
    Schema::withoutForeignKeyConstraints(function (): void {
        DB::table('password_reset_tokens')->truncate();
        DB::table('users')->truncate();
    });
});

/**
 * The other admin's session: transaction open and rows of the active admins locked.
 */
function otherAdminSession(): Connection
{
    $other = DB::connection(OTHER_SESSION);
    $other->beginTransaction();
    $other->select("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id FOR UPDATE");

    return $other;
}

function deactivateInSession(Connection $session, User $user, User $actor): void
{
    $session->table('users')->where('id', $user->id)->update([
        'is_active' => false,
        'deactivated_at' => now(),
        'deactivated_by' => $actor->id,
    ]);
}

/**
 * @return list<int>
 */
function activeAdminIds(): array
{
    return User::query()->where('role', Role::Admin->value)->where('is_active', true)->orderBy('id')
        ->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
}

it('M01-CA5: a deactivation waits while another operation holds the admins', function () {
    $anna = User::factory()->admin()->create();
    $bruno = User::factory()->admin()->create();

    otherAdminSession();

    expect(fn () => app(DeactivateUser::class)->handle($bruno, $anna))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($bruno->fresh()?->is_active)->toBeTrue();
});

it('M01-CA5: two admins who deactivate each other at the same time leave one admin active', function () {
    $anna = User::factory()->admin()->create();
    $bruno = User::factory()->admin()->create();

    // Anna deactivates Bruno: her transaction has locked the admins and not committed yet.
    $annaSession = otherAdminSession();
    deactivateInSession($annaSession, $bruno, $anna);

    // At the same time Bruno deactivates Anna: he has to wait for her.
    expect(fn () => app(DeactivateUser::class)->handle($anna, $bruno))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    // Anna commits; Bruno tries again and finds Anna as the last active admin.
    $annaSession->commit();

    expect(fn () => app(DeactivateUser::class)->handle($anna, $bruno))
        ->toThrow(UserActionNotAllowed::class, 'deve restare almeno un admin attivo');

    expect(activeAdminIds())->toBe([$anna->id]);
});

it('M01-CA5: a change of role and a deactivation at the same time leave one admin active', function () {
    $anna = User::factory()->admin()->create();
    $bruno = User::factory()->admin()->create();
    $carla = User::factory()->admin()->create();

    // Carla deactivates Bruno, not committed yet.
    $carlaSession = otherAdminSession();
    deactivateInSession($carlaSession, $bruno, $carla);
    // ...and Carla is moved to manager in the same transaction (two admins removed by her).
    $carlaSession->table('users')->where('id', $carla->id)->update(['role' => Role::Manager->value]);

    // Meanwhile Bruno moves Anna to manager: he waits.
    $toManager = new UserDetailsData($anna->first_name, $anna->last_name, $anna->email, Role::Manager, $anna->phone);
    expect(fn () => app(UpdateUser::class)->handle($anna, $toManager, $bruno))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    $carlaSession->commit();

    // After the commit Anna is the last active admin: the change of role is refused.
    expect(fn () => app(UpdateUser::class)->handle($anna, $toManager, $bruno))
        ->toThrow(UserActionNotAllowed::class, 'deve restare almeno un admin attivo');

    expect(activeAdminIds())->toBe([$anna->id])
        ->and($anna->fresh()?->role)->toBe(Role::Admin);
});
