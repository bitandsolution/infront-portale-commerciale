<?php

namespace App\Enums;

/**
 * Status of a calendar import (F-09, FL-02), stored in calendar_imports.status (C8): the preview
 * waits for corrections and confirmation; a confirmed or discarded import is read-only.
 */
enum CalendarImportStatus: string
{
    case Preview = 'preview';
    case Confirmed = 'confirmed';
    case Discarded = 'discarded';

    /**
     * Italian label (lang/it/sports.php).
     */
    public function label(): string
    {
        $label = __('sports.import_statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
