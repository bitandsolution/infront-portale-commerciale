<?php

namespace App\Enums;

/**
 * Status of a proposal (M07 §4; D-095, D-376), stored in proposals.status (C8).
 *
 * - draft: built by the agent in the configurator, editable and deletable (M07 RB-08);
 * - submitted: waiting for approval (F5);
 * - approved: approved, with frozen versions and commitments (F5);
 * - rejected: rejected, the agent corrects and resubmits it (D-099, F5);
 * - cancelled: approved and then cancelled by the admin, commitments released (D-095, F5).
 *
 * No "historical": nothing is migrated (D-227, D-376).
 */
enum ProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Italian label (lang/it/proposals.php).
     */
    public function label(): string
    {
        $label = __('proposals.statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
