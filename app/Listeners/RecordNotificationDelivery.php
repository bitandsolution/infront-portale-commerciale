<?php

namespace App\Listeners;

use App\Domain\Notifications\Services\Notifications;
use App\Mail\PortalMail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

/**
 * Delivery of the portal e-mails in notification_logs (D-209; PR-08), found by the header that
 * PortalMail adds with the id of the row. E-mails without the header are ignored.
 *
 * - MessageSending: a try is about to start (attempts + 1).
 * - MessageSent: the mail server accepted the message (status "sent", provider's message id).
 */
class RecordNotificationDelivery
{
    public function __construct(private readonly Notifications $notifications) {}

    public function handleSending(MessageSending $event): void
    {
        $id = self::logId($event->message);

        if ($id !== null) {
            $this->notifications->markAttempt($id);
        }
    }

    public function handleSent(MessageSent $event): void
    {
        $id = self::logId($event->message);

        if ($id !== null) {
            $this->notifications->markSent($id, $event->sent->getMessageId());
        }
    }

    private static function logId(Email $message): ?int
    {
        $value = $message->getHeaders()->get(PortalMail::LOG_HEADER)?->getBodyAsString();

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
