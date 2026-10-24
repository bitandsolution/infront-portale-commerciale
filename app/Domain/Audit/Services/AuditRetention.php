<?php

namespace App\Domain\Audit\Services;

use App\Models\AuditEvent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Retention of the audit trail (RB-A2, M10-CA7; D-128, D-210, D-211, D-283), run every night by
 * audit:prune:
 * - deletes the events older than 5 years;
 * - clears the IP address of the events older than config('audit.ip_retention_days'), the event
 *   staying.
 *
 * Works with direct queries on the maintenance connection (MariaDB user with only DELETE and
 * UPDATE of ip_address on audit_events, R7). The cut-off dates are computed by the database with
 * the same expression as the delete trigger, so the trigger never refuses a row chosen here.
 * Rows are processed in batches, so a long backlog never holds a long lock.
 */
final class AuditRetention
{
    private const BATCH = 1000;

    /**
     * @return array{deleted: int, ip_cleared: int}
     */
    public function prune(): array
    {
        $db = $this->connection();

        $deleted = $this->inBatches(fn (): int => $db->delete(sprintf(
            'DELETE FROM audit_events WHERE occurred_at < UTC_TIMESTAMP(6) - INTERVAL %d YEAR LIMIT %d',
            AuditEvent::RETENTION_YEARS,
            self::BATCH,
        )));

        $ipCleared = $this->inBatches(fn (): int => $db->update(sprintf(
            'UPDATE audit_events SET ip_address = NULL WHERE ip_address IS NOT NULL '
            .'AND occurred_at < UTC_TIMESTAMP(6) - INTERVAL %d DAY LIMIT %d',
            self::ipRetentionDays(),
            self::BATCH,
        )));

        return ['deleted' => $deleted, 'ip_cleared' => $ipCleared];
    }

    public static function ipRetentionDays(): int
    {
        $days = config('audit.ip_retention_days');

        return is_numeric($days) ? max(1, (int) $days) : 1826;
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
