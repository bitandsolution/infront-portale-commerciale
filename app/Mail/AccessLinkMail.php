<?php

namespace App\Mail;

use App\Domain\Users\Services\AccessLinks;
use App\Enums\AccessLinkType;
use App\Enums\NotificationType;
use App\Support\Text;
use Illuminate\Mail\Mailables\Content;

/**
 * E-mail with an access link (M01 §5, §7; D-277…D-280): activation, setup again after the reset of
 * the second factor, new password after a reset by the admin or asked from the login page (PR-08).
 *
 * Queued and encrypted like every PortalMail: the link carries the token in clear.
 */
final class AccessLinkMail extends PortalMail
{
    public function __construct(
        public readonly string $firstName,
        public readonly AccessLinkType $type,
        public readonly string $url,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::forAccessLink($this->type);
    }

    public function subjectText(): string
    {
        return $this->typeText('subject');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.access-link',
            with: [
                'greeting' => Text::get('mail.greeting', ['name' => $this->firstName]),
                'intro' => $this->typeText('intro'),
                'action' => $this->typeText('action'),
                'expiry' => Text::get('mail.access_link.expiry', ['hours' => (string) AccessLinks::LIFETIME_HOURS]),
                'ignore' => $this->typeText('ignore'),
                'url' => $this->url,
            ],
        );
    }

    private function typeText(string $key): string
    {
        return Text::get('mail.access_link.'.$this->type->value.'.'.$key);
    }
}
