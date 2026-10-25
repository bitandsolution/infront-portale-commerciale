<?php

use App\Models\AuditEvent;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
| MariaDB users of database/sql/audit-users.sql (requirement R7; D-210, D-283).
|
| The script is run here as written, with test names and passwords, then every user connects with
| its own session. Creating users needs a MariaDB administrator: in CI the tests run as root; on the
| development VM (Sail user, privileges only on its databases) they are skipped.
|
| Committed rows are needed (other sessions must see them), so this suite truncates the tables
| instead of using a transaction (tests/Pest.php).
*/

const SCRIPT_USERS = [
    '__MIGRATION_USER__' => 'pr05_migrator',
    '__APP_USER__' => 'pr05_app',
    '__MAINTENANCE_USER__' => 'pr05_maintenance',
];

const SCRIPT_PASSWORD = 'Solo-per-i-test-2027';

/**
 * Statements of the script with the placeholders replaced.
 *
 * @return list<string>
 */
function auditUsersScript(): array
{
    $sql = (string) file_get_contents(base_path('database/sql/audit-users.sql'));
    $lines = array_filter(explode("\n", $sql), fn (string $line): bool => ! str_starts_with(trim($line), '--'));

    $database = DB::connection()->getDatabaseName();
    $sql = strtr(implode("\n", $lines), [
        ...SCRIPT_USERS,
        '__MIGRATION_PASSWORD__' => SCRIPT_PASSWORD,
        '__APP_PASSWORD__' => SCRIPT_PASSWORD,
        '__MAINTENANCE_PASSWORD__' => SCRIPT_PASSWORD,
        '__DATABASE__' => $database,
        '__HOST__' => '%',
    ]);

    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}

/**
 * Session of one of the users of the script, on the test database.
 */
function scriptUserConnection(string $placeholder): Connection
{
    $name = 'script_'.SCRIPT_USERS[$placeholder];
    $default = config('database.default');
    $base = config('database.connections.'.(is_string($default) ? $default : 'mariadb'));

    config(['database.connections.'.$name => [
        ...(is_array($base) ? $base : []),
        'username' => SCRIPT_USERS[$placeholder],
        'password' => SCRIPT_PASSWORD,
    ]]);

    return DB::connection($name);
}

function dropScriptUsers(): void
{
    foreach (SCRIPT_USERS as $user) {
        DB::statement("DROP USER IF EXISTS '{$user}'@'%'");
        DB::purge('script_'.$user);
    }
}

/**
 * @return array<string, mixed>
 */
function scriptAuditRow(string $occurredAt, ?string $ip = '192.0.2.10'): array
{
    return [
        'occurred_at' => $occurredAt,
        'user_id' => 1,
        'user_name' => 'Mario Rossi',
        'user_role' => 'agent',
        'area' => 'access',
        'action' => 'access.login_succeeded',
        'ip_address' => $ip,
    ];
}

/*
| Without an administrator even "DROP USER IF EXISTS" is denied: the clean-up in the catch block and
| after a skipped test must not fail, otherwise the tests fail on the VM instead of being skipped
| (PR-07, VM collaudo).
*/
beforeEach(function () {
    $this->scriptUsersCreated = false;

    try {
        dropScriptUsers();
        foreach (auditUsersScript() as $statement) {
            DB::statement($statement);
        }
        $this->scriptUsersCreated = true;
    } catch (QueryException $e) {
        try {
            dropScriptUsers();
        } catch (QueryException) {
            // No privilege to drop users either: nothing was created.
        }
        $this->markTestSkipped('Creating MariaDB users needs an administrator (root in CI): '.$e->getMessage());
    }
});

afterEach(function () {
    if ($this->scriptUsersCreated) {
        dropScriptUsers();
    }
});

it('R7: the script creates the three users and runs again without errors', function () {
    foreach (auditUsersScript() as $statement) {
        DB::statement($statement);
    }

    $grants = collect(DB::select("SHOW GRANTS FOR 'pr05_migrator'@'%'"))->flatMap(fn (object $row): array => array_values((array) $row))->implode(' ');

    expect($grants)->toContain('ALL PRIVILEGES');
});

it('M10-CA3, R7: the application user writes and reads the audit, but cannot change, delete or drop its triggers', function () {
    $app = scriptUserConnection('__APP_USER__');

    expect($app->table('audit_events')->insert(scriptAuditRow(now()->format('Y-m-d H:i:s.u'))))->toBeTrue()
        ->and($app->table('audit_events')->count())->toBe(1);

    expect(fn () => $app->update("UPDATE audit_events SET user_name = 'Altro'"))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => $app->delete('DELETE FROM audit_events'))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => $app->statement('DROP TRIGGER audit_events_before_delete'))->toThrow(QueryException::class, 'denied')
        ->and(fn () => $app->statement('DROP TABLE audit_events'))->toThrow(QueryException::class, 'denied')
        ->and(fn () => $app->statement('ALTER TABLE audit_events DROP COLUMN reason'))->toThrow(QueryException::class, 'denied')
        ->and(fn () => $app->statement('TRUNCATE TABLE audit_events'))->toThrow(QueryException::class, 'denied');

    expect(DB::table('audit_events')->count())->toBe(1);
});

it('R7: the maintenance user only deletes expired events and clears IP addresses', function () {
    DB::table('audit_events')->insert([
        scriptAuditRow(now()->subYears(6)->format('Y-m-d H:i:s.u')),
        scriptAuditRow(now()->subDays(40)->format('Y-m-d H:i:s.u')),
        scriptAuditRow(now()->format('Y-m-d H:i:s.u')),
    ]);
    $maintenance = scriptUserConnection('__MAINTENANCE_USER__');

    expect(fn () => $maintenance->table('users')->count())->toThrow(QueryException::class, 'denied')
        ->and(fn () => $maintenance->update("UPDATE audit_events SET reason = 'x'"))->toThrow(QueryException::class, 'denied')
        ->and(fn () => $maintenance->insert("INSERT INTO audit_events (occurred_at, area, action) VALUES (NOW(6), 'access', 'access.logout')"))->toThrow(QueryException::class, 'denied')
        ->and(fn () => $maintenance->delete('DELETE FROM audit_events'))->toThrow(QueryException::class, 'append-only');

    // audit:prune on the maintenance connection (D-210, D-211).
    config(['audit.maintenance_connection' => $maintenance->getName(), 'audit.ip_retention_days' => 30]);
    expect(Artisan::call('audit:prune'))->toBe(0);

    expect(AuditEvent::query()->orderBy('occurred_at')->pluck('ip_address')->all())->toBe([null, '192.0.2.10']);
});
