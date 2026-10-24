<?php

namespace App\Mail;

use App\Domain\Users\Services\AccessLinks;
use App\Enums\AccessLinkType;
use App\Support\Text;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail with an access link (activation, reset of the second factor, reset of the password).
 *
 * ⚠️ Temporary version of PR-04 (D-277): sent right away, with Laravel's plain template.
 * PR-08 replaces it with the queued e-mail, the Infront template with logo and notification_logs
 * (D-209, D-220), without changing the Actions, which only call SendAccessLink.
 */
final class AccessLinkMail extends Mailable
{
    public function __construct(
        public readonly string $firstName,
        public readonly AccessLinkType $type,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->text('subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.access-link',
            with: [
                'greeting' => Text::get('users.mail.greeting', ['name' => $this->firstName]),
                'intro' => $this->text('intro'),
                'action' => $this->text('action'),
                'expiry' => Text::get('users.mail.expiry', ['hours' => (string) AccessLinks::LIFETIME_HOURS]),
                'ignore' => Text::get('users.mail.ignore'),
                'url' => $this->url,
            ],
        );
    }

    private function text(string $key): string
    {
        return Text::get('users.mail.'.$this->type->value.'.'.$key);
    }
}
