<?php

namespace App\Mail;

use App\Enums\NotificationType;
use App\Support\Text;
use Illuminate\Mail\Mailables\Content;

/**
 * Lock after 5 failed attempts, to the user (M01 §7; D-274, D-285; PR-08): when, how long, what to
 * do. No IP address: the admins receive it in their own e-mail.
 */
final class LoginLockedMail extends PortalMail
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lockedAt,
        public readonly int $minutes,
        public readonly string $loginUrl,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::LoginLocked;
    }

    public function subjectText(): string
    {
        return Text::get('mail.login_locked.subject', ['minutes' => (string) $this->minutes]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.login-locked',
            with: [
                'greeting' => Text::get('mail.greeting', ['name' => $this->firstName]),
                'intro' => Text::get('mail.login_locked.intro', ['time' => $this->lockedAt, 'minutes' => (string) $this->minutes]),
                'yours' => Text::get('mail.login_locked.yours'),
                'notYours' => Text::get('mail.login_locked.not_yours'),
                'action' => Text::get('mail.login_locked.action'),
                'url' => $this->loginUrl,
            ],
        );
    }
}
