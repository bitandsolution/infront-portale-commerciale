<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Data\HealthResult;
use App\Support\Text;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Internal health of the portal (progettazione-tecnica §5.2.7; D-179; PR-09).
 *
 * Six checks, each independent: one failing (e.g. Redis down) does not stop the others.
 * - database: a query on the default connection;
 * - redis: a ping on the default Redis connection (sessions, cache, queues);
 * - horizon: the queue worker is running, not paused nor stopped (D-297);
 * - queues: the oldest job waiting in documents, mail and default is younger than 10 minutes;
 * - disk: the disk of storage/ is used up to 80%;
 * - scheduler: the heartbeat written every minute by the scheduler is younger than 10 minutes.
 *
 * Thresholds in config/health.php.
 */
final class HealthCheck
{
    public const QUEUE_CONNECTION = 'redis';

    public function __construct(
        private readonly MasterSupervisorRepository $masters,
        private readonly SchedulerHeartbeat $heartbeat,
    ) {}

    /**
     * @return list<HealthResult>
     */
    public function run(): array
    {
        return [
            $this->guard('database', fn (): HealthResult => $this->database()),
            $this->guard('redis', fn (): HealthResult => $this->redis()),
            $this->guard('horizon', fn (): HealthResult => $this->horizon()),
            $this->guard('queues', fn (): HealthResult => $this->queues()),
            $this->guard('disk', fn (): HealthResult => $this->disk()),
            $this->guard('scheduler', fn (): HealthResult => $this->scheduler()),
        ];
    }

    /**
     * @param  list<HealthResult>  $results
     */
    public static function healthy(array $results): bool
    {
        foreach ($results as $result) {
            if (! $result->ok) {
                return false;
            }
        }

        return true;
    }

    private function database(): HealthResult
    {
        DB::select('SELECT 1');

        return HealthResult::pass('database', Text::get('operations.health.ok'));
    }

    private function redis(): HealthResult
    {
        Redis::connection()->ping();

        return HealthResult::pass('redis', Text::get('operations.health.ok'));
    }

    private function horizon(): HealthResult
    {
        $masters = $this->masters->all();

        if ($masters === []) {
            return HealthResult::fail('horizon', Text::get('operations.health.horizon_inactive'));
        }

        foreach ($masters as $master) {
            if (($master->status ?? null) === 'paused') {
                return HealthResult::fail('horizon', Text::get('operations.health.horizon_paused'));
            }
        }

        return HealthResult::pass('horizon', Text::get('operations.health.horizon_running'));
    }

    private function queues(): HealthResult
    {
        /** @var RedisQueue $queue */
        $queue = Queue::connection(self::QUEUE_CONNECTION);
        $limit = (int) config('health.max_queue_wait_seconds');
        $waits = [];
        $late = [];

        /** @var list<string> $names */
        $names = config('health.queues');

        foreach ($names as $name) {
            $seconds = self::waitSeconds($queue->getConnection()->lindex($queue->getQueue($name), 0));
            $waits[] = Text::get('operations.health.queue_wait', ['queue' => $name, 'seconds' => (string) $seconds]);

            if ($seconds > $limit) {
                $late[] = $name;
            }
        }

        $detail = implode(', ', $waits);

        return $late === []
            ? HealthResult::pass('queues', $detail)
            : HealthResult::fail('queues', Text::get('operations.health.queues_late', [
                'queues' => implode(', ', $late),
                'seconds' => (string) $limit,
                'detail' => $detail,
            ]));
    }

    private function disk(): HealthResult
    {
        $path = (string) config('health.disk_path');
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return HealthResult::fail('disk', Text::get('operations.health.disk_unknown'));
        }

        $used = (int) ceil(($total - $free) / $total * 100);
        $limit = (int) config('health.max_disk_used_percent');
        $detail = Text::get('operations.health.disk_used', ['used' => (string) $used, 'limit' => (string) $limit]);

        return $used > $limit ? HealthResult::fail('disk', $detail) : HealthResult::pass('disk', $detail);
    }

    private function scheduler(): HealthResult
    {
        $last = $this->heartbeat->last();

        if ($last === null) {
            return HealthResult::fail('scheduler', Text::get('operations.health.scheduler_never'));
        }

        $seconds = max(0, now()->getTimestamp() - $last);
        $limit = (int) config('health.max_scheduler_silence_seconds');
        $detail = Text::get('operations.health.scheduler_last', ['seconds' => (string) $seconds]);

        return $seconds > $limit ? HealthResult::fail('scheduler', $detail) : HealthResult::pass('scheduler', $detail);
    }

    /**
     * Seconds the job has been waiting, from the time it was pushed (Horizon's "pushedAt", Laravel's
     * "createdAt"). An empty queue waits 0 seconds.
     */
    private static function waitSeconds(mixed $payload): int
    {
        if (! is_string($payload) || $payload === '') {
            return 0;
        }

        $data = json_decode($payload, true);
        $pushedAt = is_array($data) ? ($data['pushedAt'] ?? $data['createdAt'] ?? null) : null;

        if (! is_numeric($pushedAt)) {
            return 0;
        }

        return max(0, now()->getTimestamp() - (int) floor((float) $pushedAt));
    }

    /**
     * Runs one check; an exception makes it fail with the class of the error, never with its message
     * (it may carry a host name or a password of a connection string).
     *
     * @param  callable(): HealthResult  $check
     */
    private function guard(string $name, callable $check): HealthResult
    {
        try {
            return $check();
        } catch (Throwable $exception) {
            return HealthResult::fail($name, Text::get('operations.health.error', ['error' => class_basename($exception)]));
        }
    }
}
