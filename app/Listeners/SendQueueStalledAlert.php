<?php

namespace App\Listeners;

use App\Domain\Operations\Services\OperationsAlerts;
use Laravel\Horizon\Events\LongWaitDetected;
use Throwable;

/**
 * A queue waits longer than the threshold of config/horizon.php "waits", 10 minutes
 * (progettazione-tecnica §5.2.7; D-298; PR-09): e-mail to the active admins, at most one an hour for
 * the same queue. The event is raised by the running Horizon master; Horizon stopped altogether is
 * caught by /health.
 */
class SendQueueStalledAlert
{
    public function __construct(private readonly OperationsAlerts $alerts) {}

    public function handle(LongWaitDetected $event): void
    {
        try {
            $this->alerts->queueStalled((string) $event->queue, (int) $event->seconds);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
