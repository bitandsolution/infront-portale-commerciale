<?php

namespace App\Enums;

/**
 * Kind of competition (D-185), stored in competitions.type (C8). Only league matches count for the
 * proposals (RB-01, D-033): cups exist in the model but are not counted.
 */
enum CompetitionType: string
{
    case League = 'league';
    case Cup = 'cup';

    /**
     * Italian label (lang/it/sports.php).
     */
    public function label(): string
    {
        $label = __('sports.competition_types.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
