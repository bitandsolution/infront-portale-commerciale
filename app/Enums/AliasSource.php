<?php

namespace App\Enums;

/**
 * Where a club alias comes from (D-185), stored in club_aliases.source (C8): typed by the admin in
 * the club page, saved while resolving an unknown name in a calendar import (FL-02 step 3, F1-04),
 * or brought from the old system by the one-off command of F1-06 (D-318).
 */
enum AliasSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Legacy = 'legacy';

    /**
     * Italian label (lang/it/sports.php).
     */
    public function label(): string
    {
        $label = __('sports.alias_sources.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
