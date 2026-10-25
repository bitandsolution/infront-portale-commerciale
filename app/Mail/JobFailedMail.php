<?php

namespace App\Mail;

use App\Enums\NotificationType;
use App\Support\Text;
use Illuminate\Mail\Mailables\Content;

/**
 * A queued job failed after its last try, to each active admin (progettazione-tecnica §5.2.7,
 * §5.5.1; D-298; PR-09): which job, which queue, when and the error, with the link to the failed
 * jobs of the Horizon panel. At most one e-mail an hour for the same kind of job.
 *
 * Sent at once with Notifications::sendNow(), not through the queue.
 */
final class JobFailedMail extends PortalMail
{
    public function __construct(
        public readonly string $adminFirstName,
        public readonly string $jobName,
        public readonly string $queueName,
        public readonly string $failedAt,
        public readonly string $error,
        public readonly string $panelUrl,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::JobFailed;
    }

    public function subjectText(): string
    {
        return Text::get('mail.job_failed.subject', ['queue' => $this->queueName]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.job-failed',
            with: [
                'greeting' => Text::get('mail.greeting', ['name' => $this->adminFirstName]),
                'intro' => Text::get('mail.job_failed.intro', ['time' => $this->failedAt, 'queue' => $this->queueName]),
                'details' => [
                    Text::get('mail.job_failed.job') => $this->jobName,
                    Text::get('mail.job_failed.queue') => $this->queueName,
                    Text::get('mail.job_failed.error') => $this->error,
                ],
                'note' => Text::get('mail.job_failed.note'),
                'action' => Text::get('mail.job_failed.action'),
                'url' => $this->panelUrl,
            ],
        );
    }
}
