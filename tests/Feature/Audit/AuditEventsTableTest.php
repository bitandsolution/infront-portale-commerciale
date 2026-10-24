<?php

use App\Domain\Audit\Exceptions\AuditEventImmutable;
use App\Models\AuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| audit_events append-only (RB-A1, M10-CA3; D-210, D-283; CLAUDE.md §11: trigger tests).
|
| The triggers refuse UPDATE and DELETE to every MariaDB user (these tests run as the user of the
| test database, root in CI), with only the two exceptions of the retention: clearing the IP and
| deleting events older than 5 years. The code refuses them even earlier (AuditEvent).
*/

/**
 * Event inserted directly in the table, as written by a SQL client.
 */
function rawAuditEvent(Carbon $occurredAt, ?string $ip = '192.0.2.10'): int
{
    return DB::table('audit_events')->insertGetId([
        'occurred_at' => $occurredAt->format('Y-m-d H:i:s.u'),
        'user_id' => 7,
        'user_name' => 'Mario Rossi',
        'user_role' => 'agent',
        'area' => 'access',
        'action' => 'access.login_failed',
        'object_type' => 'user',
        'object_id' => 7,
        'object_label' => 'Mario Rossi (mario.rossi@example.test)',
        'reason' => 'invalid_credentials',
        'ip_address' => $ip,
        'request_id' => 'ABC234',
    ]);
}

it('M10 §4.2: has the columns of progettazione-tecnica §5.3.6, without foreign keys', function () {
    expect(Schema::getColumnListing('audit_events'))->toEqualCanonicalizing([
        'id', 'occurred_at', 'user_id', 'user_name', 'user_role', 'area', 'action', 'object_type',
        'object_id', 'object_label', 'old_values', 'new_values', 'reason', 'ip_address', 'request_id',
    ])
        ->and(Schema::getForeignKeys('audit_events'))->toBe([]);

    $indexes = collect(Schema::getIndexes('audit_events'))->pluck('columns')->all();
    expect($indexes)->toContain(['occurred_at'])
        ->toContain(['object_type', 'object_id'])
        ->toContain(['user_id', 'occurred_at'])
        ->toContain(['area', 'action', 'occurred_at']);
});

it('M10-CA3: refuses an UPDATE written directly in SQL', function () {
    $id = rawAuditEvent(now());

    expect(fn () => DB::update("UPDATE audit_events SET user_name = 'Altro' WHERE id = ?", [$id]))
        ->toThrow(QueryException::class, 'append-only');
    expect(fn () => DB::update('UPDATE audit_events SET occurred_at = ? WHERE id = ?', [now()->subYears(6)->format('Y-m-d H:i:s.u'), $id]))
        ->toThrow(QueryException::class, 'append-only');

    // Clearing the IP together with another column is refused as well.
    expect(fn () => DB::update("UPDATE audit_events SET ip_address = NULL, reason = 'x' WHERE id = ?", [$id]))
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('audit_events')->find($id))
        ->user_name->toBe('Mario Rossi')
        ->ip_address->toBe('192.0.2.10');
});

it('M10-CA3: refuses a DELETE written directly in SQL, also of the whole table', function () {
    $id = rawAuditEvent(now()->subYears(4));

    expect(fn () => DB::delete('DELETE FROM audit_events WHERE id = ?', [$id]))
        ->toThrow(QueryException::class, 'append-only');
    expect(fn () => DB::delete('DELETE FROM audit_events'))
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('audit_events')->count())->toBe(1);
});

it('M10-CA3: refuses changes and deletions through the model, before reaching the database', function () {
    $id = rawAuditEvent(now());
    $event = AuditEvent::query()->findOrFail($id);

    $event->user_name = 'Altro';
    expect(fn () => $event->save())->toThrow(AuditEventImmutable::class)
        ->and(fn () => $event->delete())->toThrow(AuditEventImmutable::class)
        ->and(fn () => AuditEvent::query()->whereKey($id)->update(['user_name' => 'Altro']))->toThrow(AuditEventImmutable::class)
        ->and(fn () => AuditEvent::query()->whereKey($id)->delete())->toThrow(AuditEventImmutable::class)
        ->and(fn () => AuditEvent::query()->whereKey($id)->increment('object_id'))->toThrow(AuditEventImmutable::class);

    expect(DB::table('audit_events')->find($id))->user_name->toBe('Mario Rossi');
});

it('D-211: lets only the IP address be cleared, the event staying', function () {
    $id = rawAuditEvent(now());

    expect(DB::update('UPDATE audit_events SET ip_address = NULL WHERE id = ?', [$id]))->toBe(1);

    expect(DB::table('audit_events')->find($id))
        ->ip_address->toBeNull()
        ->user_name->toBe('Mario Rossi');
});

it('RB-A2: lets only the events older than 5 years be deleted', function () {
    $old = rawAuditEvent(now()->subYears(5)->subDay());
    $recent = rawAuditEvent(now()->subYears(5)->addDay());

    expect(DB::delete('DELETE FROM audit_events WHERE id = ?', [$old]))->toBe(1);
    expect(fn () => DB::delete('DELETE FROM audit_events WHERE id = ?', [$recent]))
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('audit_events')->pluck('id')->all())->toBe([$recent]);
});
