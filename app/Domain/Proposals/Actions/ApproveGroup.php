<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\AppliedDiscount;
use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ApprovalCheck;
use App\Domain\Proposals\Services\Discounts;
use App\Domain\Proposals\Services\DraftRowsWriter;
use App\Domain\Proposals\Services\GroupInventory;
use App\Enums\AuditAction;
use App\Enums\DiscountType;
use App\Enums\InventoryResource;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\InventoryCommitment;
use App\Models\Proposal;
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
 *    written (the forcing is F5);
 * 4. for every variant: club rows rewritten with the current prices (D-409), discount on the total
 *    (D-408), client frozen, working version frozen as version 1 (D-198), one commitment per
 *    resource and club row with the contract period (D-062, D-192);
 * 5. status "Approvata", history "discount_set" and "approved", audit proposal.approved.
 *
 * The document (DM-04) and the e-mail to the agent (F5) arrive later.
 */
final class ApproveGroup
{
    public function __construct(
        private readonly ApprovalCheck $check,
        private readonly GroupInventory $inventory,
        private readonly Discounts $discounts,
        private readonly DraftRowsWriter $rows,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  array<int, DiscountInput>  $discounts  proposal id → discount of the variant
     */
    public function handle(ProposalGroup $group, array $discounts, User $actor): void
    {
        DB::transaction(function () use ($group, $discounts, $actor): void {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->with(['season', 'workingVersion', 'client'])
                ->get();

            if ($variants->isEmpty() || $variants->contains(fn (Proposal $variant): bool => $variant->status !== ProposalStatus::Submitted)) {
                throw ProposalsActionNotAllowed::groupNotSubmitted();
            }

            /** @var Proposal $first */
            $first = $variants->first();
            $season = $first->season;

            if (! $season instanceof Season) {
                throw ProposalsActionNotAllowed::groupNotSubmitted();
            }

            $this->lockCapacities($season, $variants->all());
            $review = $this->check->review($variants);

            if (! $review->canBeApproved()) {
                throw ProposalsActionNotAllowed::approvalBlocked($review->problems);
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
                $this->approve($group, $variant, $season, $evaluation, $applied[$variant->id], $actor);
            }
        }, 3);
    }

    private function approve(ProposalGroup $group, Proposal $variant, Season $season, DraftEvaluation $evaluation, AppliedDiscount $discount, User $actor): void
    {
        /** @var ProposalVersion $version */
        $version = $variant->workingVersion;
        $client = $variant->client;
        $now = now();

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
            'number' => ((int) ProposalVersion::query()->where('proposal_id', $variant->id)->max('number')) + 1,
            'frozen_at' => $now,
            'frozen_by' => $actor->id,
        ])->save();

        $this->commit($season, $variant, $version, $evaluation, $actor);

        $variant->forceFill([
            'status' => ProposalStatus::Approved,
            'decided_at' => $now,
            'decided_by' => $actor->id,
            'current_version_id' => $version->id,
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
            ],
        );
    }

    /**
     * One commitment per resource of every club row (D-192), with the contract period of the version.
     */
    private function commit(Season $season, Proposal $variant, ProposalVersion $version, DraftEvaluation $evaluation, User $actor): void
    {
        foreach ($version->clubs()->get() as $row) {
            $club = $evaluation->club($row->club_id);

            if ($club === null) {
                continue;
            }

            foreach ($this->inventory->demand($club->data) as $item) {
                InventoryCommitment::query()->create([
                    'season_id' => $season->id,
                    'club_id' => $row->club_id,
                    'proposal_id' => $variant->id,
                    'proposal_version_id' => $version->id,
                    'proposal_version_club_id' => $row->id,
                    'resource_type' => $item['resource'],
                    'inventory_pool_id' => $item['resource'] === InventoryResource::Pool ? $item['valueId'] : null,
                    'partnership_tier_id' => $item['resource'] === InventoryResource::Partnership ? $item['valueId'] : null,
                    'hospitality_variant_id' => $item['resource'] === InventoryResource::Hospitality ? $item['valueId'] : null,
                    'quantity' => $item['quantity'],
                    'starts_on' => $version->starts_on,
                    'ends_on' => $version->ends_on,
                    'created_by' => $actor->id,
                ]);
            }
        }
    }

    /**
     * D-216: the capacity rows of the clubs of the group, locked table by table in order of id.
     *
     * @param  array<int, Proposal>  $variants
     */
    private function lockCapacities(Season $season, array $variants): void
    {
        $versionIds = [];

        foreach ($variants as $variant) {
            if ($variant->workingVersion !== null) {
                $versionIds[] = $variant->workingVersion->id;
            }
        }

        $clubIds = ProposalVersionClub::query()->whereIn('proposal_version_id', $versionIds)->distinct()->pluck('club_id')->all();

        if ($clubIds === []) {
            return;
        }

        foreach (InventoryResource::cases() as $resource) {
            $model = $resource->capacityModel();
            $model::query()
                ->where('season_id', $season->id)
                ->whereIn('club_id', $clubIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
        }
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
