<?php

namespace App\Enums;

/**
 * Area of an audit event (M10 §4.1), stored in audit_events.area (C8). The other areas of M10
 * (proposals, documents, catalog, inventory, sports, clients, content) are added by the slices that
 * write their events. "settings" (PR-07) covers the changes of the system settings by the admin;
 * "sports" (F1) the sports registry: seasons, competitions, editions, then clubs, stadiums,
 * enrollments, matches and calendar imports (D-321); "catalog" (F2) the catalogue and the price lists
 * (D-337); "inventory" (F2-04) the capacities of the inventory, then the commitments (D-337);
 * "clients" (F3) the commodity sectors, then the clients (D-356); "proposals" (F4) the drafts of the
 * configurator, then the life cycle of F5 (D-372, D-376); "content" (DM-02) the slide library
 * (D-406), then the KPI of F6; "documents" (DM-04) the PDF documents of the approved groups.
 */
enum AuditArea: string
{
    case Access = 'access';
    case Users = 'users';
    case Settings = 'settings';
    case Sports = 'sports';
    case Catalog = 'catalog';
    case Inventory = 'inventory';
    case Clients = 'clients';
    case Proposals = 'proposals';
    case Content = 'content';
    case Documents = 'documents';

    /**
     * Italian label (lang/it/audit.php).
     */
    public function label(): string
    {
        $label = __('audit.areas.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
