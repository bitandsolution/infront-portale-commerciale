<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Content\Data\CustomSlide;
use App\Domain\Content\Data\DocumentSelection;
use App\Domain\Documents\Actions\RequestDocument;
use App\Domain\Proposals\Data\AppliedDiscount;
use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\InventoryNeed;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ApprovalCheck;
use App\Domain\Proposals\Services\Discounts;
use App\Domain\Proposals\Services\DraftRowsWriter;
use App\Domain\Proposals\Services\GroupInventory;
use App\Domain\Proposals\Services\ReviewLock;
use App\Domain\Settings\Services\Settings;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\DiscountType;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * Approval of a group of variants by admin or manager (M07 §4, FL-01, F-04, F-05, RB-01…RB-05, CA1,
 * CA2; D-027, D-041, D-097, D-103, D-192, D-216, D-395, D-396, D-401, D-408, D-409; DM-03b), in one
 * transaction, repeated up to 3 times on a deadlock (D-216):
 *
 * 1. lock of the group and of its variants (in order of id): every variant must still be "In
 *    approvazione", so a second click or a decision taken meanwhile is refused;
 * 2. lock of the capacity rows of the clubs of the group, table by table in order of id (two
 *    approvals on the same clubs wait for each other instead of reading the same residual);
 * 3. check (ApprovalCheck): every variant evaluated on the current list and residuals, and what the
 *    variants ask together within the residuals; any problem blocks the approval and nothing is
 *    written, unless the admin forces it (F5-03, below);
 * 4. for every variant: club rows rewritten with the current prices (D-409), discount on the total
 *    (D-408), client frozen, slides chosen for the document and slides composed with blocks copied
 *    from the group (D-419, DM-07), working
 *    version frozen as version 1 (D-198), one commitment per resource and club row with the contract
 *    period (D-062, D-192);
 * 5. status "Approvata", history "discount_set" and "approved", audit proposal.approved;
 * 6. the PDF document of the group is requested (RequestDocument, DM-04): its job runs after the
 *    commit, so a failed generation never undoes the approval (D-416).
 *
 * Forcing (F5-03; M07 F-05, RB-06, CA4; M04 F-10, CA8; D-064, D-070, D-459…D-461): with a reason
 * (at least forced_reason_min_length characters) the admin approves a group whose only problems are
 * quantities beyond the residual of managed resources (ApprovalReview::canBeForced()); any other
 * problem still blocks. The commitments on the resources beyond the residual are written with
 * is_forced and the reason, the versions of the variants that ask for them with is_forced; history
 * "forced" before "approved" and audit proposal.forced with the resources. When the residuals are
 * enough again at that moment the group is approved without forcing.
 *
 * The e-mail to the agent arrives with F5.
 */
final class ApproveGroup
{
    public function __construct(
        private readonly ApprovalCheck $check,
        private readonly GroupInventory $inventory,
        private readonly Discounts $discounts,
        private readonly DraftRowsWriter $rows,
        private readonly AuditLog $audit,
        private readonly RequestDocument $requestDocument,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<int, DiscountInput>  $discounts  proposal id → discount of the variant
     * @param  string|null  $forcedReason  F5-03: the reason of the admin who forces the approval
     * @return bool whether the approval was forced
     */
    public function handle(ProposalGroup $group, array $discounts, User $actor, ?string $forcedReason = null): bool
    {
        if ($forcedReason !== null) {
            if (! Ability::ProposalsForce->allows($actor->role)) {
                throw ProposalsActionNotAllowed::forceNotAllowed();
            }

            $forcedReason = trim($forcedReason);
            $min = $this->settings->forcedReasonMinLength();

            if (mb_strlen($forcedReason) < $min) {
                throw ProposalsActionNotAllowed::forcedReasonTooShort($min);
            }
        }

        return DB::transaction(function () use ($group, $discounts, $actor, $forcedReason): bool {
            $locked = ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $selection = $locked->documentSelection();
            // DM-07: the slides composed with blocks are frozen with the versions, like the choice.
            $customSlides = array_values($locked->customSlides()->with('section')->get()
                ->map(fn (ProposalCustomSlide $slide): array => CustomSlide::fromModel($slide)->toArray())
                ->all());
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->with(['season', 'workingVersion', 'client'])
                ->get();

            if ($variants->isEmpty() || $variants->contains(fn (Proposal $variant): bool => $variant->status !== ProposalStatus::Submitted)) {
                throw ProposalsActionNotAllowed::groupNotSubmitted();
            }

            // F5-01 (D-446): refused while another user holds the lock of the work; freed below.
            ReviewLock::guard($variants, $actor);

            /** @var Proposal $first */
            $first = $variants->first();
            $season = $first->season;

            if (! $season instanceof Season) {
                throw ProposalsActionNotAllowed::groupNotSubmitted();
            }

            $this->inventory->lockCapacities($season, self::clubIds($variants->all()));
            $review = $this->check->review($variants);

            /** @var array<string, InventoryNeed> $forced key of the need → need beyond the residual */
            $forced = [];

            if (! $review->canBeApproved()) {
                if ($forcedReason === null) {
                    throw ProposalsActionNotAllowed::approvalBlocked($review->problems);
                }

                if (! $review->canBeForced()) {
                    throw ProposalsActionNotAllowed::notForceable($review->blocking);
                }

                foreach ($review->forceable() as $need) {
                    $forced[$need->key()] = $need;
                }
            }

            // The discounts are checked before anything is written.
            $applied = [];

            foreach ($review->variants as $variant) {
                /** @var DraftEvaluation $evaluation */
                $evaluation = $review->evaluation($variant);
                $input = $discounts[$variant->id] ?? new DiscountInput;
                $applied[$variant->id] = $this->discounts->apply($evaluation->listTotal, $input->type, $input->value);
            }

            foreach ($review->variants as $variant) {
                /** @var DraftEvaluation $evaluation */
                $evaluation = $review->evaluation($variant);
                $this->approve($group, $variant, $season, $evaluation, $applied[$variant->id], $selection, $customSlides, $actor, $forced, $forcedReason);
            }

            $this->requestDocument->handle($group, $actor);

            return $forced !== [];
        }, 3);
    }

    /**
     * @param  list<array<string, mixed>>  $customSlides
     * @param  array<string, InventoryNeed>  $forced
     */
    private function approve(ProposalGroup $group, Proposal $variant, Season $season, DraftEvaluation $evaluation, AppliedDiscount $discount, DocumentSelection $selection, array $customSlides, User $actor, array $forced, ?string $forcedReason): void
    {
        /** @var ProposalVersion $version */
        $version = $variant->workingVersion;
        $client = $variant->client;
        $now = now();
        $variantForced = $this->forcedNeeds($evaluation, $forced);
        $isForced = $variantForced !== [] && $forcedReason !== null;

        // D-409: the rows take the current prices, then the version is frozen with them.
        $this->rows->write($version, $version->clubs()->with('seats')->get(), $evaluation, $season);

        $version->forceFill([
            ...DraftRowsWriter::versionTotals($evaluation),
            'discount_type' => $discount->type,
            'discount_value' => $discount->value,
            'discount_amount' => $discount->isNone() ? null : $discount->amount,
            'final_total' => $discount->finalTotal,
            'client_name' => $client?->company_name,
            'client_logo_file_id' => $client?->logo_file_id,
            'commodity_sector_id' => $client?->commodity_sector_id,
            'sector_free_text' => $client?->sector_free_text,
            ...$selection->columns(),
            'custom_slides' => $customSlides,
            'is_forced' => $isForced,
            'forced_reason' => $isForced ? $forcedReason : null,
            'number' => ((int) ProposalVersion::query()->where('proposal_id', $variant->id)->max('number')) + 1,
            'frozen_at' => $now,
            'frozen_by' => $actor->id,
        ])->save();

        $this->inventory->commit($season, $variant, $version, $evaluation, $actor, $isForced ? $forced : [], $forcedReason);

        $variant->forceFill([
            'status' => ProposalStatus::Approved,
            'decided_at' => $now,
            'decided_by' => $actor->id,
            'current_version_id' => $version->id,
            'review_locked_by' => null,
            'review_locked_at' => null,
            'lock_version' => $variant->lock_version + 1,
        ])->save();

        if (! $discount->isNone()) {
            ProposalEvent::query()->create([
                'proposal_id' => $variant->id,
                'proposal_version_id' => $version->id,
                'type' => ProposalEventType::DiscountSet,
                'user_id' => $actor->id,
                'notes' => self::discountNote($discount),
            ]);
        }

        if ($isForced) {
            ProposalEvent::query()->create([
                'proposal_id' => $variant->id,
                'proposal_version_id' => $version->id,
                'type' => ProposalEventType::Forced,
                'user_id' => $actor->id,
                'notes' => $forcedReason,
            ]);

            $this->audit->record(
                AuditAction::ProposalForced,
                $actor,
                AuditSubject::proposal($variant),
                [],
                [
                    'proposal_group_id' => $group->id,
                    'variant_letter' => $variant->variant_letter,
                    'forced_reason' => $forcedReason,
                    'resources' => implode('; ', array_map(ApprovalCheck::shortfall(...), $variantForced)),
                ],
            );
        }

        ProposalEvent::query()->create([
            'proposal_id' => $variant->id,
            'proposal_version_id' => $version->id,
            'type' => ProposalEventType::Approved,
            'user_id' => $actor->id,
        ]);

        $this->audit->record(
            AuditAction::ProposalApproved,
            $actor,
            AuditSubject::proposal($variant),
            ['status' => ProposalStatus::Submitted->value],
            [
                'status' => ProposalStatus::Approved->value,
                'proposal_group_id' => $group->id,
                'variant_letter' => $variant->variant_letter,
                'version' => $version->number,
                'list_total' => $discount->listTotal,
                'discount_type' => $discount->type?->value,
                'discount_value' => $discount->value,
                'discount_amount' => $discount->isNone() ? null : $discount->amount,
                'final_total' => $discount->finalTotal,
                'is_forced' => $isForced,
            ],
        );
    }

    /**
     * The needs beyond the residual that the clubs of a variant ask for (F5-03).
     *
     * @param  array<string, InventoryNeed>  $forced
     * @return list<InventoryNeed>
     */
    private function forcedNeeds(DraftEvaluation $evaluation, array $forced): array
    {
        $needs = [];

        foreach ($evaluation->clubs as $club) {
            foreach ($this->inventory->demand($club->data) as $item) {
                $key = $club->club->id.'|'.$item['resource']->value.'|'.$item['valueId'];

                if (isset($forced[$key]) && ! in_array($forced[$key], $needs, true)) {
                    $needs[] = $forced[$key];
                }
            }
        }

        return $needs;
    }

    /**
     * The clubs of the working versions of the variants, for the lock of the capacities (D-216).
     *
     * @param  array<int, Proposal>  $variants
     * @return list<int>
     */
    private static function clubIds(array $variants): array
    {
        $versionIds = [];

        foreach ($variants as $variant) {
            if ($variant->workingVersion !== null) {
                $versionIds[] = $variant->workingVersion->id;
            }
        }

        /** @var list<int> $ids */
        $ids = ProposalVersionClub::query()->whereIn('proposal_version_id', $versionIds)->distinct()->pluck('club_id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        return $ids;
    }

    private static function discountNote(AppliedDiscount $discount): string
    {
        $values = [
            'value' => Amounts::italian((string) $discount->value),
            'amount' => Amounts::italian($discount->amount),
            'percent' => Amounts::italian($discount->percent),
            'final' => Amounts::italian($discount->finalTotal),
        ];

        return $discount->type === DiscountType::Percent
            ? Text::get('proposals.event_notes.discount_percent', $values)
            : Text::get('proposals.event_notes.discount_amount', $values);
    }
}
