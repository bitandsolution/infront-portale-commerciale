<?php

namespace App\Domain\Operations\Services;

use App\Domain\Notifications\Services\Notifications;
use App\Enums\Role;
use App\Mail\JobFailedMail;
use App\Mail\QueueStalledMail;
use App\Models\User;
use App\Support\Text;
use App\Support\Users\UserPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alerts of the queues to the active admins (progettazione-tecnica §5.2.7, §5.5.1; D-298; PR-09).
 *
 * - A job failed after its last try: one e-mail an hour at most for the same kind of job.
 * - A queue waits longer than 10 minutes (Horizon's LongWaitDetected): one e-mail an hour at most
 *   for the same queue.
 *
 * The e-mails leave at once (Notifications::sendNow()), not through the "mail" queue: the alert may
 * be about that queue. Horizon stopped altogether raises no event: /health answers 503 and the
 * external uptime service warns Antonio. The errors also reach Sentry through the exception handler.
 */
final class OperationsAlerts
{
    /** One alert an hour for the same job or queue: a broken mail server would flood the admins. */
    public const THROTTLE_SECONDS = 3600;

    public function __construct(private readonly Notifications $notifications) {}

    /**
     * Returns false when an alert for the same job was already sent in the last hour.
     */
    public function jobFailed(string $jobName, string $queue, Throwable $exception): bool
    {
        if (! Cache::add('operations:alert:job-failed:'.sha1($jobName), true, self::THROTTLE_SECONDS)) {
            return false;
        }

        $failedAt = self::now();
        $error = self::tableCell(Notifications::errorText($exception));
        $job = self::tableCell($jobName);

        foreach ($this->admins() as $admin) {
            $this->notifications->sendNow($admin, new JobFailedMail(
                adminFirstName: $admin->first_name,
                jobName: $job,
                queueName: $queue,
                failedAt: $failedAt,
                error: $error,
                panelUrl: url(self::panelPath().'/failed'),
            ));
        }

        return true;
    }

    /**
     * Returns false when an alert for the same queue was already sent in the last hour.
     */
    public function queueStalled(string $queue, int $seconds): bool
    {
        if (! Cache::add('operations:alert:queue-stalled:'.$queue, true, self::THROTTLE_SECONDS)) {
            return false;
        }

        $detectedAt = self::now();
        $minutes = max(1, intdiv($seconds + 59, 60));

        foreach ($this->admins() as $admin) {
            $this->notifications->sendNow($admin, new QueueStalledMail(
                adminFirstName: $admin->first_name,
                queueName: $queue,
                minutes: $minutes,
                detectedAt: $detectedAt,
                panelUrl: url(self::panelPath().'/dashboard'),
            ));
        }

        return true;
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(): Collection
    {
        return User::query()
            ->where('role', Role::Admin->value)
            ->where('is_active', true)
            ->whereNotNull('activated_at')
            ->orderBy('id')
            ->get();
    }

    private static function panelPath(): string
    {
        $path = config('horizon.path');

        return is_string($path) && $path !== '' ? $path : 'horizon';
    }

    private static function now(): string
    {
        $now = now()->setTimezone(UserPresenter::TIMEZONE);

        return Text::get('mail.datetime', ['date' => $now->format('d/m/Y'), 'time' => $now->format('H:i')]);
    }

    /**
     * One line without the separator of the Markdown table of the e-mail.
     */
    private static function tableCell(string $value): string
    {
        return Str::squish(str_replace('|', '/', $value));
    }
}
