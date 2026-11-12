<?php

namespace App\Support\Proposals;

use App\Domain\Proposals\Data\AppliedDiscount;
use App\Domain\Proposals\Data\ApprovalReview;
use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\InventoryNeed;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ApprovalCheck;
use App\Domain\Proposals\Services\Discounts;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Domain\Proposals\Services\DraftRepricing;
use App\Enums\DiscountType;
use App\Enums\DocumentType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Text;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Props of the pages of a group of variants (D-395, D-396, D-408…D-412; DM-03a, DM-03b): the queue
 * "Da approvare" and the detail of a group, the same for admin, manager and the agent of the group.
 * Every number comes from the server: before the approval the variants are evaluated on the current
 * list and residuals (DraftEvaluator, ApprovalCheck), after it the frozen version is shown. Discounts,
 * availability and the actions of the review reach only admin and manager (D-011, D-086).
 */
final class ProposalGroupsPresenter
{
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(
        private readonly DraftEvaluator $evaluator,
        private readonly ApprovalCheck $check,
        private readonly DraftRepricing $repricing,
        private readonly Discounts $discounts,
    ) {}

    /**
     * Status of a group: the one of its variants; with mixed statuses the approved one (after the
     * choice of the client, D-410), then the rejected one (a variant added to a rejected group), then
     * the draft.
     *
     * @param  Collection<int, Proposal>  $variants
     */
    public static function status(Collection $variants): ProposalStatus
    {
        $statuses = $variants->map(fn (Proposal $variant): ProposalStatus => $variant->status)->unique(fn (ProposalStatus $status): string => $status->value);

        if ($statuses->count() === 1) {
            /** @var ProposalStatus $only */
            $only = $statuses->first();

            return $only;
        }

        return match (true) {
            $statuses->contains(ProposalStatus::Approved) => ProposalStatus::Approved,
            $statuses->contains(ProposalStatus::Rejected) => ProposalStatus::Rejected,
            default => ProposalStatus::Draft,
        };
    }

    /**
     * Tone of the status pill (never colour alone: the label is always written).
     */
    public static function tone(ProposalStatus $status): string
    {
        return match ($status) {
            ProposalStatus::Approved => 'success',
            ProposalStatus::Submitted, ProposalStatus::Rejected => 'notice',
            ProposalStatus::Draft, ProposalStatus::Cancelled, ProposalStatus::NotChosen => 'muted',
        };
    }

    /**
     * Rows of the queue "Da approvare" (M07 F-01): the oldest sending first, with the problems that
     * would block the approval now (ApprovalCheck, D-409).
     *
     * @param  Collection<int, ProposalGroup>  $groups  with proposals.season, proposals.workingVersion
     * @return list<array<string, mixed>>
     */
    public function queue(Collection $groups): array
    {
        $rows = [];

        foreach ($groups as $group) {
            /** @var Collection<int, Proposal> $variants */
            $variants = $group->proposals;
            $first = $variants->sortBy('variant_letter')->first();

            if (! $first instanceof Proposal) {
                continue;
            }

            $review = $this->check->review($variants);
            $many = $variants->count() > 1;

            $rows[] = [
                'id' => $group->id,
                'number' => $first->number,
                'title' => Text::get('proposals.label', ['number' => (string) $first->number]),
                'submittedAt' => self::moment($variants->max('submitted_at')),
                'client' => $group->client?->company_name,
                'agent' => $group->agent->first_name.' '.$group->agent->last_name,
                'variants' => $variants->count(),
                'clubs' => $this->clubNames($review),
                'totals' => array_map(fn (Proposal $variant): array => [
                    'letter' => $many ? $variant->variant_letter : null,
                    'total' => Amounts::italian(self::listTotal($review->evaluation($variant))),
                ], $review->variants),
                'problems' => $review->problems,
                'url' => route('proposals.groups.show', $group),
            ];
        }

        return $rows;
    }

    /**
     * Detail of a group for its viewer (D-411): header, every variant with its summary (current
     * evaluation before the approval, frozen version after it), the history and the actions the
     * viewer can take. For admin and manager, while the group waits: prices changed since the sending
     * (D-409), availability of the group, discounts with their preview (D-408).
     *
     * @param  array<int, DiscountInput>  $discounts  typed discounts to preview, by proposal id
     * @param  array<int, string>  $discountErrors  by proposal id
     * @return array<string, mixed>
     */
    public function group(ProposalGroup $group, User $viewer, array $discounts = [], array $discountErrors = []): array
    {
        $variants = $group->proposals()
            ->with(['client.sector', 'season', 'workingVersion.clubs.seats', 'workingVersion.clubs.club', 'currentVersion', 'agent'])
            ->orderBy('variant_letter')
            ->get();
        /** @var Proposal $first */
        $first = $variants->first();
        $status = self::status($variants);
        $isAgent = $viewer->can('submit', $group);
        $isReviewer = $viewer->can('review', ProposalGroup::class);
        $waiting = $status === ProposalStatus::Submitted;
        $many = $variants->count() > 1;
        $review = $waiting && $isReviewer ? $this->check->review($variants) : null;
        $decidedBy = $first->decided_by !== null ? User::query()->find($first->decided_by) : null;
        $entries = [];
        $clubIds = [];

        foreach ($variants as $variant) {
            $frozen = self::frozenVersion($variant);
            $evaluation = $frozen === null ? ($review?->evaluation($variant) ?? $this->evaluator->evaluateProposal($variant)) : null;
            $draft = match (true) {
                $frozen !== null => ProposalsPresenter::frozen($variant, $frozen),
                $variant->workingVersion !== null => ProposalsPresenter::draft($variant, $variant->agent, $evaluation),
                default => null,
            };

            foreach (is_array($draft) && is_array($draft['clubs'] ?? null) ? $draft['clubs'] : [] as $club) {
                if (is_array($club) && is_int($club['clubId'] ?? null)) {
                    $clubIds[$club['clubId']] = true;
                }
            }

            $entries[] = [
                'id' => $variant->id,
                'letter' => $many ? $variant->variant_letter : null,
                'number' => $variant->number,
                'status' => $variant->status->value,
                'statusLabel' => $variant->status->label(),
                'draft' => $draft,
                'editUrl' => $isAgent && $variant->isEditable() ? route('proposals.edit', ['proposal' => $variant, 'passo' => 5]) : null,
                'repricing' => $isReviewer && $waiting && $evaluation !== null ? $this->repricing($variant, $evaluation) : null,
                'discount' => $isReviewer ? $this->discount($variant, $frozen, $evaluation, $discounts[$variant->id] ?? null, $discountErrors[$variant->id] ?? null) : null,
            ];
        }

        $documentType = DocumentType::forClubs(count($clubIds));
        $allApproved = $variants->every(fn (Proposal $variant): bool => $variant->status === ProposalStatus::Approved);
        $chosen = $variants->contains(fn (Proposal $variant): bool => $variant->status === ProposalStatus::NotChosen)
            ? $variants->firstWhere('status', ProposalStatus::Approved)
            : null;

        return [
            'id' => $group->id,
            'number' => $first->number,
            'title' => Text::get('proposals.label', ['number' => (string) $first->number]),
            'client' => $group->client?->company_name,
            'season' => $group->season?->code,
            'agent' => $group->agent->first_name.' '.$group->agent->last_name,
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'statusTone' => self::tone($status),
            'documentLabel' => $documentType?->label(),
            'submittedAt' => self::moment($variants->max('submitted_at')),
            'decidedAt' => in_array($status, [ProposalStatus::Rejected, ProposalStatus::Approved], true) ? self::moment($first->decided_at) : null,
            'decidedBy' => in_array($status, [ProposalStatus::Rejected, ProposalStatus::Approved], true) && $decidedBy !== null ? $decidedBy->first_name.' '.$decidedBy->last_name : null,
            'rejectionReason' => $status === ProposalStatus::Rejected ? $first->last_rejection_reason : null,
            'chosenLetter' => $chosen instanceof Proposal ? $chosen->variant_letter : null,
            'variants' => $entries,
            'availability' => $review !== null ? array_map(self::need(...), $review->needs) : null,
            'problems' => $review !== null ? $review->problems : [],
            'history' => $this->history($variants),
            'canSubmit' => $isAgent && $variants->every(fn (Proposal $variant): bool => $variant->isEditable()),
            'submitUrl' => route('proposals.groups.submit', $group),
            'canReject' => $isReviewer && $waiting,
            'rejectUrl' => route('proposals.groups.reject', $group),
            'canApprove' => $isReviewer && $waiting,
            'approveUrl' => route('proposals.groups.approve', $group),
            'canChoose' => $isReviewer && $many && $allApproved,
            'chooseUrl' => route('proposals.groups.choose', $group),
            'backUrl' => $isAgent ? route('pages.proposals_mine') : route('pages.proposals_pending'),
            'backLabel' => Text::get($isAgent ? 'ui.proposals.groups.back_mine' : 'ui.proposals.groups.back_pending'),
        ];
    }

    /**
     * The discount of a variant for admin and manager: frozen after the approval, otherwise the
     * typed one with its preview (D-408), calculated by the server (Discounts).
     *
     * @return array<string, mixed>
     */
    private function discount(Proposal $variant, ?ProposalVersion $frozen, ?DraftEvaluation $evaluation, ?DiscountInput $input, ?string $error): array
    {
        if ($frozen !== null) {
            $applied = $this->discounts->apply($frozen->list_total, $frozen->discount_type, $frozen->discount_value);

            return [...self::applied($applied), 'editable' => false, 'error' => null];
        }

        $listTotal = $evaluation !== null ? $evaluation->listTotal : '0.00';
        $input ??= new DiscountInput;
        $applied = null;

        if ($error === null) {
            try {
                $applied = $this->discounts->apply($listTotal, $input->type, $input->value);
            } catch (ProposalsActionNotAllowed $exception) {
                $error = $exception->getMessage();
            }
        }

        return [
            ...self::applied($applied ?? $this->discounts->apply($listTotal, null, null)),
            'type' => $input->type !== null ? $input->type->value : '',
            'value' => $input->value !== null ? Amounts::italian($input->value) : '',
            'editable' => $variant->status === ProposalStatus::Submitted,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function applied(AppliedDiscount $applied): array
    {
        return [
            'type' => $applied->type !== null ? $applied->type->value : '',
            'value' => $applied->value !== null ? Amounts::italian($applied->value) : '',
            'hasDiscount' => ! $applied->isNone(),
            'listTotal' => Amounts::italian($applied->listTotal),
            'amount' => Amounts::italian($applied->amount),
            'percent' => Amounts::italian($applied->percent),
            'finalTotal' => Amounts::italian($applied->finalTotal),
            'label' => match ($applied->type) {
                DiscountType::Percent => Text::get('ui.proposals.approval.discount_percent_label', ['value' => Amounts::italian((string) $applied->value)]),
                DiscountType::Amount => Text::get('ui.proposals.approval.discount_amount_label', ['percent' => Amounts::italian($applied->percent)]),
                null => null,
            },
        ];
    }

    /**
     * D-409: the prices saved at the sending against the current ones ("da € X a € Y").
     *
     * @return array{clubs: list<array{name: string, text: string}>, total: string}|null
     */
    private function repricing(Proposal $variant, DraftEvaluation $evaluation): ?array
    {
        $version = $variant->workingVersion;

        return $version !== null
            ? ProposalsPresenter::repricing($this->repricing->compare($version->clubs, $version->list_total, $evaluation))
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function need(InventoryNeed $need): array
    {
        return [
            'club' => $need->club->name,
            'resource' => $need->name,
            'requested' => ApprovalCheck::quantity($need->resource, $need->requested),
            'remaining' => ApprovalCheck::quantity($need->resource, $need->remaining),
            'letters' => $need->letters !== [] ? implode(', ', $need->letters) : null,
            'covered' => $need->isCovered(),
        ];
    }

    /**
     * The frozen version of an approved or not chosen variant (D-198), null before the approval.
     */
    private static function frozenVersion(Proposal $variant): ?ProposalVersion
    {
        return in_array($variant->status, [ProposalStatus::Approved, ProposalStatus::NotChosen], true) ? $variant->currentVersion : null;
    }

    /**
     * History of every variant of the group, the most recent first (M07 F-10, D-199).
     *
     * @param  Collection<int, Proposal>  $variants
     * @return list<array{at: string|null, variant: string|null, label: string, user: string, notes: string|null}>
     */
    private function history(Collection $variants): array
    {
        $letters = $variants->count() > 1 ? $variants->pluck('variant_letter', 'id') : collect();

        return array_values(ProposalEvent::query()
            ->whereIn('proposal_id', $variants->pluck('id'))
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ProposalEvent $event): array => [
                'at' => self::moment($event->created_at),
                'variant' => is_string($letter = $letters->get($event->proposal_id)) ? $letter : null,
                'label' => $event->type->label(),
                'user' => $event->user->first_name.' '.$event->user->last_name,
                'notes' => $event->notes,
            ])
            ->all());
    }

    /**
     * Names of the clubs of the variants, without repetitions.
     */
    private function clubNames(ApprovalReview $review): string
    {
        $names = [];

        foreach ($review->evaluations as $evaluation) {
            foreach ($evaluation instanceof DraftEvaluation ? $evaluation->clubs : [] as $club) {
                $names[$club->club->id] = $club->club->short_name;
            }
        }

        return implode(', ', $names);
    }

    private static function listTotal(?DraftEvaluation $evaluation): string
    {
        return $evaluation !== null ? $evaluation->listTotal : '0';
    }

    private static function moment(mixed $moment): ?string
    {
        return $moment instanceof Carbon ? $moment->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i') : null;
    }
}
