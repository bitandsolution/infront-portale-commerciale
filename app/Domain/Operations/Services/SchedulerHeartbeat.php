<?php

namespace App\Domain\Operations\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Heartbeat of the scheduler (PR-09): written every minute by a scheduled task (routes/console.php),
 * read by the health check. A heartbeat older than 10 minutes means that cron does not run
 * "php artisan schedule:run" (R2), so the retention, the clean-ups and the Horizon snapshots stop.
 */
final class SchedulerHeartbeat
{
    public const CACHE_KEY = 'operations:scheduler-heartbeat';

    /** Kept a day: long enough to tell "stopped" from "never ran". */
    private const TTL_SECONDS = 86400;

    public function beat(): void
    {
        Cache::put(self::CACHE_KEY, now()->getTimestamp(), self::TTL_SECONDS);
    }

    /**
     * Unix time of the last heartbeat, null if none in the last day.
     */
    public function last(): ?int
    {
        // The Redis store gives numbers back as strings.
        $value = Cache::get(self::CACHE_KEY);

        return is_numeric($value) ? (int) $value : null;
    }
}
