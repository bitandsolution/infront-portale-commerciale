<?php

namespace App\Enums;

/**
 * Area of an audit event (M10 §4.1), stored in audit_events.area (C8). The other areas of M10
 * (proposals, documents, catalog, inventory, sports, clients, content) are added by the slices that
 * write their events.
 */
enum AuditArea: string
{
    case Access = 'access';
    case Users = 'users';

    /**
     * Italian label (lang/it/audit.php).
     */
    public function label(): string
    {
        $label = __('audit.areas.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
