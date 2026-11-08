<?php

namespace App\Domain\Proposals\Services;

use Illuminate\Support\Facades\DB;

/**
 * Number of a new proposal (M07 RB-09, D-371): the next value of the MariaDB sequence
 * proposal_numbers, which starts from 204 (the last number of the old system is 203). Assigned when
 * the draft is born (D-375), unchanged across versions and resubmissions, never reused: a sequence is
 * not rolled back, so a failed transaction or a deleted draft leaves a gap (traced in the audit).
 */
final class ProposalNumbers
{
    public function next(): int
    {
        $row = DB::selectOne('SELECT NEXT VALUE FOR proposal_numbers AS number');

        return (int) (is_object($row) && property_exists($row, 'number') ? $row->number : 0);
    }
}
