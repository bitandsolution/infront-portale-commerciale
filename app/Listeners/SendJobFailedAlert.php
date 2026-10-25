<?php

namespace App\Listeners;

use App\Domain\Operations\Services\OperationsAlerts;
use Illuminate\Queue\Events\JobFailed;
use Throwable;

/**
 * A queued job failed after its last try (progettazione-tecnica §5.2.7; D-298; PR-09): e-mail to the
 * active admins, at most one an hour for the same kind of job.
 *
 * Jobs run on the "sync" connection are skipped: they fail inside the request that ran them, whose
 * error page and log already report it (and so do the tests, which run the queue synchronously).
 * A failure here is reported and never hides the original failure of the job.
 */
class SendJobFailedAlert
{
    public function __construct(private readonly OperationsAlerts $alerts) {}

    public function handle(JobFailed $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        try {
            $this->alerts->jobFailed($event->job->resolveName(), $event->job->getQueue(), $event->exception);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
