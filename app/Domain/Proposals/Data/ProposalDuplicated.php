<?php

namespace App\Domain\Proposals\Data;

use App\Models\Proposal;

/**
 * Outcome of "Duplica proposta" (D-091, M06-CA8; F4-05): the new draft, the prices recalculated on the
 * current list and matches compared with those of the original ("da € X a € Y", null when nothing
 * changed) and the names of the clubs of the copy that have something to correct (M06 §9); whether the copy has clubs (the page opens at step 4) or not (step 2).
 */
final readonly class ProposalDuplicated
{
    /**
     * @param  list<string>  $invalidClubs
     */
    public function __construct(
        public Proposal $proposal,
        public ?DraftPriceChanges $priceChanges,
        public array $invalidClubs,
        public bool $hasClubs,
    ) {}
}
