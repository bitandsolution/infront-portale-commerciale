<?php

namespace App\Enums;

/**
 * Stage of a matchday (D-186), stored in matchdays.stage (C8). Only the regular stage counts for the
 * proposals (RB-01, D-033): playoff and playout do not.
 */
enum MatchdayStage: string
{
    case Regular = 'regular';
    case Playoff = 'playoff';
    case Playout = 'playout';

    /**
     * Italian label (lang/it/sports.php).
     */
    public function label(): string
    {
        $label = __('sports.matchday_stages.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
