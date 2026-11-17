<?php

namespace App\Mail;

use App\Enums\NotificationType;
use Illuminate\Mail\Mailables\Content;

/**
 * Every e-mail about a proposal (M01 §7, M07 F-08, M07-CA5, M01-CA6; D-105, D-221, D-474…D-478;
 * F5-06): sending for approval to admin and managers; approval, rejection, new version, cancellation
 * and "document ready" to the agent; document not generated to the admins.
 *
 * The texts are written by App\Domain\Proposals\Services\ProposalNotifications from lang/it/mail.php:
 * one e-mail per group, the details of the group (number, client, season, variants, author, reason),
 * never an amount, only the link to the detail of the group (the PDF is downloaded after the login).
 */
final class ProposalMail extends PortalMail
{
    /**
     * @param  array<string, string>  $details  label → value, in this order
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly int $proposal,
        public readonly string $subjectLine,
        public readonly string $greeting,
        public readonly string $intro,
        public readonly array $details,
        public readonly ?string $note,
        public readonly string $action,
        public readonly string $url,
    ) {}

    public function notificationType(): NotificationType
    {
        return $this->type;
    }

    public function subjectText(): string
    {
        return $this->subjectLine;
    }

    public function proposalId(): ?int
    {
        return $this->proposal;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.proposal',
            with: [
                'greeting' => $this->greeting,
                'intro' => $this->intro,
                'details' => $this->details,
                'note' => $this->note,
                'action' => $this->action,
                'url' => $this->url,
            ],
        );
    }
}
