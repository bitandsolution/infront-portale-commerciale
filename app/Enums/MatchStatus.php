<?php

namespace App\Enums;

/**
 * Status of a match (D-185, D-319), stored in matches.status (C8). A cancelled match is excluded
 * from the count of the proposals (M02 §9) and can be restored; matches are never deleted.
 */
enum MatchStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    /**
     * Italian label (lang/it/sports.php).
     */
    public function label(): string
    {
        $label = __('sports.match_statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
