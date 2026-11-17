<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Notifications\Services\Notifications;
use App\Enums\DocumentNotification;
use App\Enums\NotificationType;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Mail\ProposalMail;
use App\Models\Document;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\User;
use App\Support\Text;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The e-mails of the proposals (M01 §7, M07 F-08, M07-CA5, M01-CA6; D-105, D-221, D-474…D-478;
 * F5-06), written with Notifications::send() inside the transaction of the caller: a rollback sends
 * nothing (PortalMail is queued after the commit).
 *
 * - One e-mail per group, about its variant A (notification_logs.proposal_id), with number, client,
 *   season, the letters of the variants concerned, the author of the decision and its reason; never an
 *   amount (D-478). The link opens the detail of the group: the PDF is downloaded after the login.
 * - Sending for approval: to the active and activated admins and managers, never to its author (D-475).
 * - Outcomes: to the agent of the group, only if active and activated (D-475).
 * - Approval and new version wait for the document (D-221, D-474): documentReady() and documentFailed()
 *   are called by DocumentGenerator with the pending e-mail of the document.
 */
final class ProposalNotifications
{
    public function __construct(private readonly Notifications $notifications) {}

    /**
     * "Invia in approvazione" of a group, also after a rejection ($again).
     */
    public function submitted(ProposalGroup $group, User $agent, bool $again): void
    {
        $variants = $this->variants($group);
        $number = $this->number($variants);
        $key = $again ? 'again' : '';
        $recipients = User::query()
            ->whereIn('role', [Role::Admin->value, Role::Manager->value])
            ->where('is_active', true)
            ->whereNotNull('activated_at')
            ->whereKeyNot($agent->id)
            ->orderBy('id')
            ->get();

        foreach ($recipients as $recipient) {
            $this->send(
                $recipient,
                NotificationType::ProposalSubmitted,
                $group,
                $variants,
                Text::get('mail.proposals.submitted.subject'.($key !== '' ? '_'.$key : ''), ['number' => $number]),
                Text::get('mail.proposals.submitted.intro'.($key !== '' ? '_'.$key : ''), ['agent' => $agent->fullName(), 'number' => $number]),
                [Text::get('mail.proposals.agent') => $agent->fullName()],
                Text::get('mail.proposals.submitted.note'),
            );
        }
    }

    public function rejected(ProposalGroup $group, User $actor, ?string $reason): void
    {
        $variants = $this->variants($group);
        $agent = $this->agent($group);

        if ($agent === null) {
            return;
        }

        $number = $this->number($variants);
        $this->send(
            $agent,
            NotificationType::ProposalRejected,
            $group,
            $variants,
            Text::get('mail.proposals.rejected.subject', ['number' => $number]),
            Text::get('mail.proposals.rejected.intro', ['number' => $number]),
            [
                Text::get('mail.proposals.decided_by') => $actor->fullName(),
                Text::get('mail.proposals.reason') => $reason ?? Text::get('mail.proposals.no_reason'),
            ],
            Text::get('mail.proposals.rejected.note'),
        );
    }

    /**
     * @param  list<Proposal>  $cancelled  the variants cancelled (D-473: after the choice, only the chosen one)
     */
    public function cancelled(ProposalGroup $group, User $actor, string $reason, array $cancelled): void
    {
        $agent = $this->agent($group);

        if ($agent === null || $cancelled === []) {
            return;
        }

        $variants = $this->variants($group);
        $number = $this->number($variants);
        $this->send(
            $agent,
            NotificationType::ProposalCancelled,
            $group,
            new Collection($cancelled),
            Text::get('mail.proposals.cancelled.subject', ['number' => $number]),
            Text::get('mail.proposals.cancelled.intro', ['number' => $number]),
            [
                Text::get('mail.proposals.decided_by') => $actor->fullName(),
                Text::get('mail.proposals.reason') => $reason,
            ],
            Text::get('mail.proposals.cancelled.note'),
            $variants,
        );
    }

    /**
     * The document is ready: the e-mail that waited for it leaves with the PDF (D-221, D-474, D-477).
     * Nothing when the group has no approved variant any more (cancelled meanwhile).
     */
    public function documentReady(Document $document): void
    {
        $pending = $document->pending_notification;
        $group = ProposalGroup::query()->find($document->proposal_group_id);

        if ($pending === null || $group === null) {
            return;
        }

        $approved = $this->approved($group);

        if ($approved->isEmpty()) {
            return;
        }

        $this->toAgent($group, $approved, $pending, withDocument: true);
    }

    /**
     * The document could not be generated (D-221, D-476): the admins are told; the e-mail that waited
     * for it leaves without the PDF, and the agent will be told when a regeneration is ready (D-477).
     *
     * @return DocumentNotification|null what the next document of the group still owes to the agent
     */
    public function documentFailed(Document $document): ?DocumentNotification
    {
        $pending = $document->pending_notification;
        $group = ProposalGroup::query()->find($document->proposal_group_id);

        if ($group === null) {
            return null;
        }

        $approved = $this->approved($group);

        if ($approved->isEmpty()) {
            return null;
        }

        $variants = $this->variants($group);
        $number = $this->number($variants);
        $admins = User::query()
            ->where('role', Role::Admin->value)
            ->where('is_active', true)
            ->whereNotNull('activated_at')
            ->orderBy('id')
            ->get();

        foreach ($admins as $admin) {
            $this->send(
                $admin,
                NotificationType::ProposalDocumentFailed,
                $group,
                $approved,
                Text::get('mail.proposals.document_failed.subject', ['number' => $number]),
                Text::get('mail.proposals.document_failed.intro', ['number' => $number]),
                [Text::get('mail.proposals.agent') => $this->agentName($group)],
                Text::get('mail.proposals.document_failed.note'),
                $variants,
            );
        }

        if ($pending === DocumentNotification::Approved || $pending === DocumentNotification::NewVersion) {
            $this->toAgent($group, $approved, $pending, withDocument: false);

            return DocumentNotification::DocumentReady;
        }

        return $pending;
    }

    /**
     * @param  Collection<int, Proposal>  $approved
     */
    private function toAgent(ProposalGroup $group, Collection $approved, DocumentNotification $pending, bool $withDocument): void
    {
        $agent = $this->agent($group);

        if ($agent === null) {
            return;
        }

        $variants = $this->variants($group);
        $number = $this->number($variants);
        $document = $withDocument ? 'document' : 'no_document';

        if ($pending === DocumentNotification::NewVersion) {
            $this->newVersion($agent, $group, $variants, $approved, $document);

            return;
        }

        $kind = $pending === DocumentNotification::Approved ? 'approved' : 'document_ready';
        $this->send(
            $agent,
            $pending === DocumentNotification::Approved ? NotificationType::ProposalApproved : NotificationType::ProposalDocumentReady,
            $group,
            $approved,
            Text::get('mail.proposals.'.$kind.'.subject', ['number' => $number]),
            Text::get('mail.proposals.'.$kind.'.intro', ['number' => $number]),
            $pending === DocumentNotification::Approved
                ? [Text::get('mail.proposals.decided_by') => $this->userName($approved->first()?->decided_by)]
                : [],
            Text::get($pending === DocumentNotification::Approved ? 'mail.proposals.approved.'.$document : 'mail.proposals.document_ready.note'),
            $variants,
        );
    }

    /**
     * The variant whose current version was frozen last is the one just changed (SaveNewVersion).
     *
     * @param  Collection<int, Proposal>  $variants
     * @param  Collection<int, Proposal>  $approved
     */
    private function newVersion(User $agent, ProposalGroup $group, Collection $variants, Collection $approved, string $document): void
    {
        $versionId = ProposalVersion::query()
            ->whereIn('id', $approved->pluck('current_version_id')->filter()->all())
            ->orderByDesc('frozen_at')
            ->orderByDesc('id')
            ->value('id');
        /** @var Proposal $changed */
        $changed = $approved->firstWhere('current_version_id', $versionId) ?? $approved->first();
        $version = $changed->currentVersion;
        $number = (string) $changed->number;
        $replace = ['number' => $number, 'version' => (string) ($version->number ?? ''), 'letter' => (string) $changed->variant_letter];

        $this->send(
            $agent,
            NotificationType::ProposalNewVersion,
            $group,
            new Collection([$changed]),
            Text::get('mail.proposals.new_version.subject', $replace),
            Text::get($variants->count() > 1 ? 'mail.proposals.new_version.intro_variant' : 'mail.proposals.new_version.intro', $replace),
            [
                Text::get('mail.proposals.version') => (string) ($version->number ?? ''),
                Text::get('mail.proposals.decided_by') => $this->userName($version?->frozen_by),
            ],
            Text::get('mail.proposals.new_version.'.$document),
            $variants,
            $number,
        );
    }

    /**
     * @param  Collection<int, Proposal>  $concerned  the variants the e-mail is about (their letters)
     * @param  array<string, string>  $extra  details after client and season
     * @param  Collection<int, Proposal>|null  $all  every variant of the group (the e-mail is about the A)
     */
    private function send(
        User $recipient,
        NotificationType $type,
        ProposalGroup $group,
        Collection $concerned,
        string $subject,
        string $intro,
        array $extra,
        ?string $note,
        ?Collection $all = null,
        ?string $number = null,
    ): void {
        $all ??= $concerned;
        /** @var Proposal $first */
        $first = $all->first();
        $group->loadMissing(['client', 'season']);

        $details = [
            Text::get('mail.proposals.number') => Text::get('mail.proposals.number_value', ['number' => $number ?? (string) $first->number]),
            Text::get('mail.proposals.client') => (string) $group->client?->company_name,
            Text::get('mail.proposals.season') => (string) $group->season?->name,
        ];

        if ($all->count() > 1) {
            $details[Text::get('mail.proposals.variants')] = $concerned->pluck('variant_letter')->implode(', ');
        }

        foreach ($extra as $label => $value) {
            $details[$label] = $value;
        }

        $this->notifications->send($recipient, new ProposalMail(
            type: $type,
            proposal: $first->id,
            subjectLine: $subject,
            greeting: Text::get('mail.greeting', ['name' => $recipient->first_name]),
            intro: $intro,
            details: array_map(self::cell(...), $details),
            note: $note,
            action: Text::get('mail.proposals.action'),
            url: route('proposals.groups.show', $group),
        ));
    }

    /**
     * The variants of the group in the order of their letter.
     *
     * @return Collection<int, Proposal>
     */
    private function variants(ProposalGroup $group): Collection
    {
        return Proposal::query()->where('proposal_group_id', $group->id)->orderBy('variant_letter')->get();
    }

    /**
     * @return Collection<int, Proposal>
     */
    private function approved(ProposalGroup $group): Collection
    {
        return Proposal::query()
            ->where('proposal_group_id', $group->id)
            ->where('status', ProposalStatus::Approved->value)
            ->with('currentVersion')
            ->orderBy('variant_letter')
            ->get();
    }

    /**
     * The number of a group is the number of its variant A.
     *
     * @param  Collection<int, Proposal>  $variants
     */
    private function number(Collection $variants): string
    {
        return (string) $variants->first()?->number;
    }

    /** The agent of the group, if he can still read the e-mail in the portal (D-475). */
    private function agent(ProposalGroup $group): ?User
    {
        return User::query()
            ->whereKey($group->agent_id)
            ->where('is_active', true)
            ->whereNotNull('activated_at')
            ->first();
    }

    private function agentName(ProposalGroup $group): string
    {
        return $this->userName($group->agent_id);
    }

    private function userName(?int $id): string
    {
        $user = $id !== null ? User::query()->find($id) : null;

        return $user?->fullName() ?? '—';
    }

    /** One line of the Markdown table: no line breaks, no column separators. */
    private static function cell(string $value): string
    {
        $value = str_replace('|', '/', Str::squish($value));

        return $value !== '' ? $value : '—';
    }
}
