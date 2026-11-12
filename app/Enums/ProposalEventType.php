<?php

namespace App\Enums;

/**
 * Type of an entry of the history of a proposal (M07 F-10, CA 6; D-199), stored in
 * proposal_events.type (C8). The history is visible with the permissions of the proposal and is
 * distinct from the technical audit (admin only). F4 writes "created" and "duplicated", F5 the rest.
 */
enum ProposalEventType: string
{
    case Created = 'created';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
    case EditedInReview = 'edited_in_review';
    case DiscountSet = 'discount_set';
    case Approved = 'approved';
    case Forced = 'forced';
    case Rejected = 'rejected';
    case Resubmitted = 'resubmitted';
    case NewVersion = 'new_version';
    case Cancelled = 'cancelled';
    case Duplicated = 'duplicated';
    case VariantAdded = 'variant_added';
    case ClientChosen = 'client_chosen';

    /**
     * Italian label (lang/it/proposals.php).
     */
    public function label(): string
    {
        $label = __('proposals.event_types.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
