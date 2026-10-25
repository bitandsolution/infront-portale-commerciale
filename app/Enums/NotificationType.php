<?php

namespace App\Enums;

/**
 * Kind of e-mail sent by the portal (M01 §7; D-209), stored in notification_logs.type (C8).
 *
 * Stable names: the register of the e-mails is filtered on them. The e-mails of the proposals
 * (new, approved, rejected, new version, cancelled) are added by slice F5.
 */
enum NotificationType: string
{
    // Access links (D-277…D-280; PR-08 self-service reset)
    case Activation = 'activation';
    case TwoFactorReset = 'two_factor_reset';
    case PasswordReset = 'password_reset';
    case PasswordForgotten = 'password_forgotten';

    // Lock after 5 failed attempts (M01 §7, D-274): to the user and to the admins
    case LoginLocked = 'login_locked';
    case LoginLockedAdmin = 'login_locked_admin';

    // Operations alerts to the admins (progettazione-tecnica §5.2.7; D-298; PR-09)
    case JobFailed = 'job_failed';
    case QueueStalled = 'queue_stalled';

    public static function forAccessLink(AccessLinkType $type): self
    {
        return match ($type) {
            AccessLinkType::Activation => self::Activation,
            AccessLinkType::TwoFactorReset => self::TwoFactorReset,
            AccessLinkType::PasswordReset => self::PasswordReset,
            AccessLinkType::PasswordForgotten => self::PasswordForgotten,
        };
    }

    /**
     * Italian label (lang/it/mail.php).
     */
    public function label(): string
    {
        $label = __('mail.types.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
