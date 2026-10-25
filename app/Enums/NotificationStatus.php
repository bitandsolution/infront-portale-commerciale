<?php

namespace App\Enums;

/**
 * State of an e-mail in notification_logs (progettazione-tecnica §5.3.6; D-209):
 * queued when accepted by the portal, sent when the mail server took it, failed after the last try.
 */
enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * Italian label (lang/it/mail.php).
     */
    public function label(): string
    {
        $label = __('mail.statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
