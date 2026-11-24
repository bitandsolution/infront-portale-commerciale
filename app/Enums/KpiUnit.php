<?php

namespace App\Enums;

/**
 * Unit of a KPI (progettazione-tecnica §5.3.5; D-203): what the integer value counts. Shown beside the
 * field; the short forms of the documents ("1 MLN", "29K") are written by the layouts of F7 (M08 §9).
 */
enum KpiUnit: string
{
    case People = 'people';
    case Broadcasts = 'broadcasts';
    case Followers = 'followers';
    case Users = 'users';
    case Contacts = 'contacts';

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.kpi_units.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
