<?php

namespace App\Mail;

use App\Domain\Notifications\Services\Notifications;
use App\Enums\NotificationType;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

/**
 * Every e-mail of the portal (M01 §7; D-209, D-220; progettazione-tecnica §5.5.1, §5.5.4).
 *
 * - Sent through the "mail" queue, never right away: 5 tries with growing waits, 60 s each.
 * - Queued only after the commit of the transaction that asked for it: a rolled-back Action sends
 *   nothing (a token that does not exist would otherwise reach the user).
 * - Encrypted in the queue (ShouldBeEncrypted): the payload in Redis, in the Horizon panel and in
 *   failed_jobs never shows a link with its token.
 * - Infront template (resources/views/vendor/mail, theme "infront"), texts from lang/it/mail.php,
 *   plain-text version generated from the same Markdown. Only links, never attachments (M01 §7).
 * - Registered in notification_logs by App\Domain\Notifications\Services\Notifications, which sets
 *   $notificationLogId; the id travels in a header so the delivery events find the row.
 *
 * Send it only with Notifications::send(), never with Mail::send() directly.
 */
#[Queue('mail')]
#[Tries(5)]
#[Timeout(60)]
#[Backoff(10, 30, 60, 120, 300)]
abstract class PortalMail extends Mailable implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    /** Header with the id of the notification_logs row (App\Listeners\RecordNotificationDelivery). */
    public const LOG_HEADER = 'X-Portal-Notification';

    public ?int $notificationLogId = null;

    abstract public function notificationType(): NotificationType;

    /**
     * Subject of the e-mail, also stored in notification_logs.
     */
    abstract public function subjectText(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText());
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->notificationLogId === null ? [] : [
            self::LOG_HEADER => (string) $this->notificationLogId,
        ]);
    }

    /**
     * Called by the queue after the last try: the row becomes "failed" with the error.
     */
    public function failed(Throwable $exception): void
    {
        app(Notifications::class)->markFailed($this->notificationLogId, $exception);
    }
}
