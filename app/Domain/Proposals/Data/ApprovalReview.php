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
     * @param  list<string>  $problems
     */
    public function __construct(
        public array $variants,
        public array $evaluations,
        public array $needs,
        public array $problems,
    ) {}

    public function canBeApproved(): bool
    {
        return $this->problems === [];
    }

    public function evaluation(Proposal $variant): ?DraftEvaluation
    {
        return $this->evaluations[$variant->id] ?? null;
    }
}
