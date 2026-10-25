<?php

namespace App\Mail;

use App\Enums\NotificationType;
use App\Support\Text;
use Illuminate\Mail\Mailables\Content;

/**
 * A queue waits longer than 10 minutes, to each active admin (progettazione-tecnica §5.2.7; D-298;
 * PR-09): which queue, the wait estimated by Horizon and the link to the panel. At most one e-mail an
 * hour for the same queue.
 *
 * Sent at once with Notifications::sendNow(): the stalled queue may be the "mail" queue itself.
 */
final class QueueStalledMail extends PortalMail
{
    public function __construct(
        public readonly string $adminFirstName,
        public readonly string $queueName,
        public readonly int $minutes,
        public readonly string $detectedAt,
        public readonly string $panelUrl,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::QueueStalled;
    }

    public function subjectText(): string
    {
        return Text::get('mail.queue_stalled.subject', ['queue' => $this->queueName]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.queue-stalled',
            with: [
                'greeting' => Text::get('mail.greeting', ['name' => $this->adminFirstName]),
                'intro' => Text::get('mail.queue_stalled.intro', [
                    'time' => $this->detectedAt,
                    'queue' => $this->queueName,
                    'minutes' => (string) $this->minutes,
                ]),
                'note' => Text::get('mail.queue_stalled.note'),
                'action' => Text::get('mail.queue_stalled.action'),
                'url' => $this->panelUrl,
            ],
        );
    }
}
