<?php

namespace App\Mail;

use App\Enums\NotificationType;
use App\Support\Text;
use Illuminate\Mail\Mailables\Content;

/**
 * Lock after 5 failed attempts, to each active admin (M01 §7; D-274, D-285; PR-08): who, when and
 * from which IP address, with the link to the user's page.
 */
final class LoginLockedAdminMail extends PortalMail
{
    public function __construct(
        public readonly string $adminFirstName,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $userRole,
        public readonly string $lockedAt,
        public readonly string $ipAddress,
        public readonly int $minutes,
        public readonly string $userUrl,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::LoginLockedAdmin;
    }

    public function subjectText(): string
    {
        return Text::get('mail.login_locked_admin.subject', ['name' => $this->userName]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.login-locked-admin',
            with: [
                'greeting' => Text::get('mail.greeting', ['name' => $this->adminFirstName]),
                'intro' => Text::get('mail.login_locked_admin.intro', ['time' => $this->lockedAt, 'minutes' => (string) $this->minutes]),
                'details' => [
                    Text::get('mail.login_locked_admin.user') => $this->userName,
                    Text::get('mail.login_locked_admin.email') => $this->userEmail,
                    Text::get('mail.login_locked_admin.role') => $this->userRole,
                    Text::get('mail.login_locked_admin.ip') => $this->ipAddress,
                ],
                'unlock' => Text::get('mail.login_locked_admin.unlock'),
                'action' => Text::get('mail.login_locked_admin.action'),
                'url' => $this->userUrl,
            ],
        );
    }
}
