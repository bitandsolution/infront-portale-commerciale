<?php

namespace App\Enums;

/**
 * Status of a season (D-185, D-317), stored in seasons.status (C8) and set by hand by the admin.
 *
 * - planned: being prepared (registry, calendars, price list);
 * - open: the season the portal works on;
 * - closed: concluded; its sports registry is read-only (no imports, enrollments or match changes).
 */
enum SeasonStatus: string
{
    case Planned = 'planned';
    case Open = 'open';
    case Closed = 'closed';

    /**
     * Italian label (lang/it/sports.php).
     */
    public function label(): string
    {
        $label = __('sports.season_statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }

    public function isReadOnly(): bool
    {
        return $this === self::Closed;
    }
}
