<?php

namespace App\Domain\Notifications\Services;

use App\Enums\NotificationStatus;
use App\Mail\PortalMail;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the e-mails of the portal and keeps their register (M01 §7, §10; D-209; PR-08).
 *
 * send() writes the notification_logs row ("queued") and queues the e-mail on the "mail" queue.
 * Called inside the transaction of an Action, the row and the e-mail share its fate: on rollback the
 * row disappears and the e-mail is never queued (PortalMail is queued after the commit).
 *
 * The row then follows the delivery: every try counts an attempt, the mail server's acceptance makes
 * it "sent" with the provider's message id (App\Listeners\RecordNotificationDelivery), the failure of
 * the last try makes it "failed" with the error (PortalMail::failed()).
 *
 * sendNow() is for the operations alerts only (PR-09): the e-mail leaves at once, without the queue,
 * because the alert may be about the queue itself being stalled.
 *
 * Nothing of the message body is stored: no link, no token.
 */
final class Notifications
{
    public const ERROR_MAX_LENGTH = 1000;

    /** The register is kept 12 months (D-209). */
    public const RETENTION_MONTHS = 12;

    public function send(User $user, PortalMail $mail): NotificationLog
    {
        $log = $this->register($user, $mail);

        Mail::to($user->email)->queue($mail);

        return $log;
    }

    /**
     * Sends the e-mail at once, outside the queue (operations alerts, PR-09). The row follows the
     * delivery like the queued e-mails; a refused message makes it "failed" and is not retried.
     */
    public function sendNow(User $user, PortalMail $mail): NotificationLog
    {
        $log = $this->register($user, $mail);

        try {
            Mail::to($user->email)->sendNow($mail);
        } catch (Throwable $exception) {
            $this->markFailed($log->id, $exception);
            report($exception);
        }

        return $log->refresh();
    }

    private function register(User $user, PortalMail $mail): NotificationLog
    {
        $log = NotificationLog::query()->create([
            'user_id' => $user->id,
            'type' => $mail->notificationType(),
            'recipient_email' => $user->email,
            'subject' => Str::limit($mail->subjectText(), 250, ''),
            'status' => NotificationStatus::Queued,
            'attempts' => 0,
            'queued_at' => now(),
        ]);

        $mail->notificationLogId = $log->id;

        return $log;
    }

    /**
     * A try is about to hand the message to the mail server.
     */
    public function markAttempt(int $id): void
    {
        NotificationLog::query()->whereKey($id)->increment('attempts');
    }

    /**
     * The mail server accepted the message. A retry from the panel after a failure ends here too.
     */
    public function markSent(int $id, ?string $providerMessageId): void
    {
        NotificationLog::query()->whereKey($id)->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId === null ? null : Str::limit($providerMessageId, 250, ''),
            'error' => null,
        ]);
    }

    /**
     * The last try failed. A message already accepted by the mail server stays "sent".
     */
    public function markFailed(?int $id, Throwable $exception): void
    {
        if ($id === null) {
            return;
        }

        NotificationLog::query()
            ->whereKey($id)
            ->where('status', '<>', NotificationStatus::Sent->value)
            ->update([
                'status' => NotificationStatus::Failed,
                'failed_at' => now(),
                'error' => self::errorText($exception),
            ]);
    }

    /**
     * Deletes the rows older than 12 months (D-209), weekly from the scheduler. Returns how many.
     */
    public function prune(): int
    {
        return NotificationLog::query()
            ->where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS))
            ->delete();
    }

    /**
     * Class and message of the exception, without addresses of web pages (a link may carry a token)
     * and cut to the column size.
     */
    public static function errorText(Throwable $exception): string
    {
        $message = preg_replace('~\b[a-z][a-z0-9+.-]*://\S+~i', '[url]', $exception->getMessage()) ?? '';

        return Str::limit(class_basename($exception).': '.trim($message), self::ERROR_MAX_LENGTH - 3);
    }
}
