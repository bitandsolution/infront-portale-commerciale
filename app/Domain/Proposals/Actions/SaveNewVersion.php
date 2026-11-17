<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Documents\Actions\RequestDocument;
use App\Domain\Proposals\Data\AppliedDiscount;
use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\InventoryNeed;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\DraftConflict;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ApprovalCheck;
use App\Domain\Proposals\Services\Discounts;
use App\Domain\Proposals\Services\DraftRowsWriter;
use App\Domain\Proposals\Services\GroupInventory;
use App\Domain\Settings\Services\Settings;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\CommitmentReleaseReason;
use App\Enums\DiscountType;
use App\Enums\DocumentNotification;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Text;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Salva la versione n+1" of an approved variant by the admin (M07 FL-04, F-06, RB-07, CA7; D-041,
 * D-096, D-198, D-216, D-444, D-448, D-464…D-467; F5-04), in one transaction repeated up to 3 times on
 * a deadlock (D-216):
 *
 * 1. lock of the group, of its variants (in order of id) and of the capacity rows of the clubs of the
 *    copy and of the previous version; the variant must be approved with its working copy and the
 *    lock_version of the page;
 * 2. check of the copy with ApprovalCheck without the commitments of the variant and with what its
 *    previous version committed (D-465): a quantity not increased never blocks, an increase must fit
 *    the residual plus the previous quantity, or be forced by the admin with a reason (F5-03 logic,
 *    D-459…D-461); every other problem blocks;
 * 3. a version equal to the current one is refused ("Nessuna modifica", D-467); a change of the
 *    discount only is a new version;
 * 4. club rows rewritten with the current list (D-464), discount on the total, client, slides chosen
 *    and slides composed copied from the previous version, frozen as version n+1 (D-198);
 * 5. the commitments of the previous version released as "superseded" and those of the new version
 *    written (D-192), forced on the resources beyond the residual (D-460);
 * 6. current version, lock_version; history "discount_set" (when the discount changes), "forced" and
 *    "new_version"; audit proposal.forced and proposal.new_version with the changed fields;
 * 7. the PDF document of the group is asked again (D-448, D-205): only the approved variants, at their
 *    last version.
 *
 * The e-mail to the agent leaves when the regenerated document is ready (F5-06; D-474).
 */
final class SaveNewVersion
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

    public function handle(Proposal $proposal, int $lockVersion, DiscountInput $discount, User $actor, ?string $forcedReason = null): ProposalVersion
    {
        if (! Ability::ProposalsManageApproved->allows($actor->role)) {
            throw ProposalsActionNotAllowed::notRevisable($proposal->number);
        }

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

        return DB::transaction(function () use ($proposal, $lockVersion, $discount, $actor, $forcedReason): ProposalVersion {
            $group = $proposal->proposal_group_id !== null
                ? ProposalGroup::query()->whereKey($proposal->proposal_group_id)->lockForUpdate()->first()
                : null;

            if (! $group instanceof ProposalGroup) {
                throw ProposalsActionNotAllowed::notUnderRevision($proposal->number);
            }

            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->with(['season', 'workingVersion', 'currentVersion', 'client'])
                ->get();
            $locked = $variants->firstWhere('id', $proposal->id);
            $working = $locked?->workingVersion;
            $previous = $locked?->currentVersion;

            if (! $locked instanceof Proposal || $locked->status !== ProposalStatus::Approved || ! $working instanceof ProposalVersion || ! $previous instanceof ProposalVersion) {
                throw ProposalsActionNotAllowed::notUnderRevision($proposal->number);
            }

            if ($locked->lock_version !== $lockVersion) {
                throw new DraftConflict;
            }

            $season = $locked->season;

            if (! $season instanceof Season) {
                throw ProposalsActionNotAllowed::notUnderRevision($locked->number);
            }

            $this->inventory->lockCapacities($season, self::clubIds([$working->id, $previous->id]));
            $committed = $this->inventory->committedBy($locked);
            $review = $this->check->review([$locked], $locked->id, $committed);

            /** @var array<string, InventoryNeed> $forced */
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

            /** @var DraftEvaluation $evaluation */
            $evaluation = $review->evaluation($locked);
            $applied = $this->discounts->apply($evaluation->listTotal, $discount->type, $discount->value);
            $previousRows = self::rows($previous);
            $before = self::values($locked, $previous, $previousRows);
            $after = [
                ...ProposalsAuditValues::draft($locked, $working),
                ...ProposalsAuditValues::clubsOf($evaluation),
                'list_total' => $evaluation->listTotal,
                ...self::discountValues($applied),
            ];

            foreach (array_keys($before) as $field) {
                if (str_starts_with($field, 'club_') && ! array_key_exists($field, $after)) {
                    $after[$field] = null;
                }
            }

            [$old, $new] = AuditLog::changes($before, $after);

            if ($new === []) {
                throw ProposalsActionNotAllowed::versionUnchanged();
            }

            $isForced = $forced !== [];
            $now = now();

            // D-464: the rows take the current list, then the copy is frozen as version n+1 with what
            // the previous version froze of the client and of the document (D-419, DM-07).
            $this->rows->write($working, $working->clubs()->with('seats')->get(), $evaluation, $season);
            $working->forceFill([
                ...DraftRowsWriter::versionTotals($evaluation),
                'discount_type' => $applied->type,
                'discount_value' => $applied->value,
                'discount_amount' => $applied->isNone() ? null : $applied->amount,
                'final_total' => $applied->finalTotal,
                'client_name' => $previous->client_name,
                'client_logo_file_id' => $previous->client_logo_file_id,
                'commodity_sector_id' => $previous->commodity_sector_id,
                'sector_free_text' => $previous->sector_free_text,
                'document_mode' => $previous->document_mode,
                'excluded_slide_ids' => $previous->excluded_slide_ids,
                'custom_slides' => $previous->custom_slides,
                'is_forced' => $isForced,
                'forced_reason' => $isForced ? $forcedReason : null,
                'number' => ((int) ProposalVersion::query()->where('proposal_id', $locked->id)->max('number')) + 1,
                'frozen_at' => $now,
                'frozen_by' => $actor->id,
            ])->save();

            $this->inventory->release($locked, $actor, CommitmentReleaseReason::Superseded);
            $this->inventory->commit($season, $locked, $working, $evaluation, $actor, $forced, $forcedReason);

            $locked->forceFill([
                'current_version_id' => $working->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->history($group, $locked, $working, $previous, $applied, $forced, $forcedReason, $actor, $old, $new);
            $this->requestDocument->handle($group, $actor, notification: DocumentNotification::NewVersion);

            return $working;
        }, 3);
    }

    /**
     * @param  array<string, InventoryNeed>  $forced
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function history(ProposalGroup $group, Proposal $variant, ProposalVersion $version, ProposalVersion $previous, AppliedDiscount $discount, array $forced, ?string $forcedReason, User $actor, array $old, array $new): void
    {
        $discountChanged = $previous->discount_type?->value !== $discount->type?->value
            || self::decimal($previous->discount_value) !== self::decimal($discount->value);

        if ($discountChanged && ! $discount->isNone()) {
            ProposalEvent::query()->create([
                'proposal_id' => $variant->id,
                'proposal_version_id' => $version->id,
                'type' => ProposalEventType::DiscountSet,
                'user_id' => $actor->id,
                'notes' => self::discountNote($discount),
            ]);
        }

        if ($forced !== [] && $forcedReason !== null) {
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
                    'version' => $version->number,
                    'forced_reason' => $forcedReason,
                    'resources' => implode('; ', array_map(ApprovalCheck::shortfall(...), array_values($forced))),
                ],
            );
        }

        ProposalEvent::query()->create([
            'proposal_id' => $variant->id,
            'proposal_version_id' => $version->id,
            'type' => ProposalEventType::NewVersion,
            'user_id' => $actor->id,
            'notes' => Text::get('proposals.event_notes.new_version', ['number' => (string) $version->number]),
        ]);

        $this->audit->record(
            AuditAction::ProposalNewVersion,
            $actor,
            AuditSubject::proposal($variant),
            ['version' => $previous->number, ...$old],
            [
                'version' => $version->number,
                'proposal_group_id' => $group->id,
                'variant_letter' => $variant->variant_letter,
                'is_forced' => $forced !== [],
                ...$new,
            ],
        );
    }

    /**
     * The fields of a frozen version compared to decide whether the new one changes anything (D-467)
     * and written in the audit: period, clubs, list total, discount.
     *
     * @param  Collection<int, ProposalVersionClub>  $rows
     * @return array<string, mixed>
     */
    public static function values(Proposal $proposal, ProposalVersion $version, Collection $rows): array
    {
        $discount = app(Discounts::class)->apply($version->list_total, $version->discount_type, $version->discount_value);

        return [
            ...ProposalsAuditValues::draft($proposal, $version),
            ...ProposalsAuditValues::clubRows($rows),
            'list_total' => $version->list_total,
            ...self::discountValues($discount),
        ];
    }

    /**
     * Club rows of a version with what the audit needs.
     *
     * @return Collection<int, ProposalVersionClub>
     */
    public static function rows(ProposalVersion $version): Collection
    {
        return $version->clubs()
            ->with(['club', 'partnershipTier', 'ledFormat', 'seats.variant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, string|null>
     */
    public static function discountValues(AppliedDiscount $discount): array
    {
        return [
            'discount_type' => $discount->type?->value,
            'discount_value' => $discount->isNone() ? null : self::decimal($discount->value),
            'final_total' => $discount->finalTotal,
        ];
    }

    private static function decimal(?string $value): ?string
    {
        return $value !== null ? Amounts::normalize($value) : null;
    }

    /**
     * @param  list<int>  $versionIds
     * @return list<int>
     */
    private static function clubIds(array $versionIds): array
    {
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
