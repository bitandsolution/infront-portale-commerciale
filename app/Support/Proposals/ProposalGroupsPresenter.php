<?php

namespace App\Support\Proposals;

use App\Domain\Proposals\Data\ClubEvaluation;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Enums\DocumentType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Text;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Props of the pages of a group of variants (D-395, D-396, D-411, D-412; DM-03a): the queue "Da
 * approvare" and the detail of a group, the same for admin, manager and the agent of the group. Every
 * number comes from the server: the clubs are evaluated on the current list and residuals
 * (DraftEvaluator), like the configurator.
 */
final class ProposalGroupsPresenter
{
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(private readonly DraftEvaluator $evaluator) {}

    /**
     * Status of a group: the one of its variants; with mixed statuses (a variant added to a rejected
     * group) the rejected one, then the draft.
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

        return $statuses->contains(ProposalStatus::Rejected) ? ProposalStatus::Rejected : ProposalStatus::Draft;
    }

    /**
     * Tone of the status pill (never colour alone: the label is always written).
     */
    public static function tone(ProposalStatus $status): string
    {
        return match ($status) {
            ProposalStatus::Approved => 'success',
            ProposalStatus::Submitted, ProposalStatus::Rejected => 'notice',
            ProposalStatus::Draft, ProposalStatus::Cancelled => 'muted',
        };
    }

    /**
     * Rows of the queue "Da approvare" (M07 F-01): the oldest sending first.
     *
     * @param  Collection<int, ProposalGroup>  $groups  with proposals.client, proposals.workingVersion
     * @return list<array<string, mixed>>
     */
    public function queue(Collection $groups): array
    {
        $rows = [];

        foreach ($groups as $group) {
            /** @var Collection<int, Proposal> $variants */
            $variants = $group->proposals;
            $first = $variants->first();

            if (! $first instanceof Proposal) {
                continue;
            }

            $evaluations = $variants->mapWithKeys(fn (Proposal $variant): array => [$variant->id => $this->evaluator->evaluateProposal($variant)]);
            $problems = [];

            foreach ($variants as $variant) {
                $evaluation = $evaluations->get($variant->id);

                if ($evaluation instanceof DraftEvaluation && $evaluation->hasErrors()) {
                    $problems[] = $this->problemLabel($variant, $evaluation, $variants->count() > 1);
                }
            }

            $rows[] = [
                'id' => $group->id,
                'number' => $first->number,
                'title' => Text::get('proposals.label', ['number' => (string) $first->number]),
                'submittedAt' => self::moment($variants->max('submitted_at')),
                'client' => $group->client?->company_name,
                'agent' => $group->agent->first_name.' '.$group->agent->last_name,
                'variants' => $variants->count(),
                'clubs' => $this->clubNames(array_values($evaluations->all())),
                'totals' => $variants->map(fn (Proposal $variant): array => [
                    'letter' => $variants->count() > 1 ? $variant->variant_letter : null,
                    'total' => Amounts::italian(($evaluation = $evaluations->get($variant->id)) instanceof DraftEvaluation ? $evaluation->listTotal : '0'),
                ])->values()->all(),
                'problems' => $problems,
                'url' => route('proposals.groups.show', $group),
            ];
        }

        return $rows;
    }

    /**
     * Detail of a group for its viewer (D-411): header, every variant with the summary of the
     * configurator (read only), the history, and the actions the viewer can take.
     *
     * @return array<string, mixed>
     */
    public function group(ProposalGroup $group, User $viewer): array
    {
        $variants = $group->proposals()->with(['client.sector', 'season', 'workingVersion', 'agent'])->get();
        /** @var Proposal $first */
        $first = $variants->first();
        $status = self::status($variants);
        $evaluations = $variants->mapWithKeys(fn (Proposal $variant): array => [$variant->id => $this->evaluator->evaluateProposal($variant)]);
        $clubIds = [];

        foreach ($evaluations as $evaluation) {
            foreach ($evaluation instanceof DraftEvaluation ? $evaluation->clubs : [] as $club) {
                $clubIds[$club->club->id] = true;
            }
        }

        $documentType = DocumentType::forClubs(count($clubIds));
        $decidedBy = $first->decided_by !== null ? User::query()->find($first->decided_by) : null;
        $isAgent = $viewer->can('submit', $group);
        $editable = $variants->every(fn (Proposal $variant): bool => $variant->isEditable());

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
            'decidedAt' => $status === ProposalStatus::Rejected ? self::moment($first->decided_at) : null,
            'decidedBy' => $status === ProposalStatus::Rejected && $decidedBy !== null ? $decidedBy->first_name.' '.$decidedBy->last_name : null,
            'rejectionReason' => $status === ProposalStatus::Rejected ? $first->last_rejection_reason : null,
            'variants' => $variants->map(function (Proposal $variant) use ($evaluations, $variants, $isAgent): array {
                $evaluation = $evaluations->get($variant->id);

                return [
                    'id' => $variant->id,
                    'letter' => $variants->count() > 1 ? $variant->variant_letter : null,
                    'number' => $variant->number,
                    'statusLabel' => $variant->status->label(),
                    'draft' => $variant->workingVersion !== null ? ProposalsPresenter::draft($variant, $variant->agent, $evaluation instanceof DraftEvaluation ? $evaluation : null) : null,
                    'editUrl' => $isAgent && $variant->isEditable() ? route('proposals.edit', ['proposal' => $variant, 'passo' => 5]) : null,
                ];
            })->values()->all(),
            'history' => $this->history($variants),
            'canSubmit' => $isAgent && $editable,
            'submitUrl' => route('proposals.groups.submit', $group),
            'canReject' => $viewer->can('review', ProposalGroup::class) && $status === ProposalStatus::Submitted,
            'rejectUrl' => route('proposals.groups.reject', $group),
            'backUrl' => $isAgent ? route('pages.proposals_mine') : route('pages.proposals_pending'),
            'backLabel' => Text::get($isAgent ? 'ui.proposals.groups.back_mine' : 'ui.proposals.groups.back_pending'),
        ];
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
     * Names of the clubs of the evaluations, without repetitions.
     *
     * @param  list<DraftEvaluation|null>  $evaluations
     */
    private function clubNames(array $evaluations): string
    {
        $names = [];

        foreach ($evaluations as $evaluation) {
            foreach ($evaluation instanceof DraftEvaluation ? $evaluation->clubs : [] as $club) {
                $names[$club->club->id] = $club->club->short_name;
            }
        }

        return implode(', ', $names);
    }

    private function problemLabel(Proposal $variant, DraftEvaluation $evaluation, bool $withLetter): string
    {
        $clubs = array_map(
            fn (ClubEvaluation $club): string => $club->club->short_name,
            array_values(array_filter($evaluation->clubs, fn (ClubEvaluation $club): bool => $club->hasErrors())),
        );
        $text = implode(', ', $clubs);

        return $withLetter ? Text::get('ui.proposals.groups.problem_variant', ['letter' => (string) $variant->variant_letter, 'clubs' => $text]) : $text;
    }

    private static function moment(mixed $moment): ?string
    {
        return $moment instanceof Carbon ? $moment->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i') : null;
    }
}
