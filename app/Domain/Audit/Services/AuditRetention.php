<?php

namespace App\Domain\Audit\Services;

use App\Domain\Settings\Services\Settings;
use App\Models\AuditEvent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Retention of the audit trail (RB-A2, M10-CA7; D-128, D-210, D-211, D-283), run every night by
 * audit:prune:
 * - deletes the events older than 5 years;
 * - clears the IP address of the events older than the setting "audit_ip_retention_days", the
 *   event staying (D-286: from PR-07 the settings table, with config('audit.ip_retention_days') as
 *   default when the row is missing).
 *
 * Works with direct queries on the maintenance connection (MariaDB user with only DELETE and
 * UPDATE of ip_address on audit_events, R7). The cut-off dates are computed by the database with
 * the same expression as the delete trigger, so the trigger never refuses a row chosen here.
 * Rows are processed in batches, so a long backlog never holds a long lock.
 *
 * The number of days is read on the default connection (Settings): the maintenance user has
 * privileges on audit_events only, and the value reaches its queries as an integer.
 */
final class AuditRetention
{
    private const BATCH = 1000;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{deleted: int, ip_cleared: int, ip_retention_days: int}
     */
    public function prune(): array
    {
        $db = $this->connection();
        $ipRetentionDays = $this->settings->auditIpRetentionDays();

        $deleted = $this->inBatches(fn (): int => $db->delete(sprintf(
            'DELETE FROM audit_events WHERE occurred_at < UTC_TIMESTAMP(6) - INTERVAL %d YEAR LIMIT %d',
            AuditEvent::RETENTION_YEARS,
            self::BATCH,
        )));

        $ipCleared = $this->inBatches(fn (): int => $db->update(sprintf(
            'UPDATE audit_events SET ip_address = NULL WHERE ip_address IS NOT NULL '
            .'AND occurred_at < UTC_TIMESTAMP(6) - INTERVAL %d DAY LIMIT %d',
            $ipRetentionDays,
            self::BATCH,
        )));

        return ['deleted' => $deleted, 'ip_cleared' => $ipCleared, 'ip_retention_days' => $ipRetentionDays];
    }

    private function connection(): ConnectionInterface
    {
        $name = config('audit.maintenance_connection');

        return DB::connection(is_string($name) && $name !== '' ? $name : null);
    }

    /**
     * @param  callable(): int  $batch
     */
    private function inBatches(callable $batch): int
    {
        $total = 0;

        do {
            $affected = $batch();
            $total += $affected;
        } while ($affected === self::BATCH);

        return $total;
    }
}
