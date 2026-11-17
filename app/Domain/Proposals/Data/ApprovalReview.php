<?php

namespace App\Domain\Proposals\Data;

use App\Models\Proposal;

/**
 * A group of variants checked for the approval (M07 F-02, RB-05; D-216, D-396, D-409; DM-03b): every
 * variant evaluated on the current list and residuals, what the variants ask together of the
 * inventory, and the problems that block the approval (empty = it can be approved).
 */
final readonly class ApprovalReview
{
    /**
     * @param  list<Proposal>  $variants  in the order of their letter
     * @param  array<int, DraftEvaluation|null>  $evaluations  proposal id → evaluation (null without a working version)
     * @param  list<InventoryNeed>  $needs
     * @param  list<string>  $problems  every problem, in the order shown
     * @param  list<string>  $blocking  the problems that not even the admin can force (F5-03, D-459)
     */
    public function __construct(
        public array $variants,
        public array $evaluations,
        public array $needs,
        public array $problems,
        public array $blocking = [],
    ) {}

    public function canBeApproved(): bool
    {
        return $this->problems === [];
    }

    /**
     * F5-03 (M07 F-05, RB-06; D-064, D-070, D-459): the only problems are quantities beyond the
     * residual of managed resources, so the admin may approve with a forcing.
     */
    public function canBeForced(): bool
    {
        return $this->problems !== [] && $this->blocking === [] && $this->forceable() !== [];
    }

    /**
     * The needs beyond the residual that a forcing covers.
     *
     * @return list<InventoryNeed>
     */
    public function forceable(): array
    {
        return array_values(array_filter($this->needs, fn (InventoryNeed $need): bool => $need->isForceable()));
    }

    public function evaluation(Proposal $variant): ?DraftEvaluation
    {
        return $this->evaluations[$variant->id] ?? null;
    }
}
