<?php

namespace App\Enums;

/**
 * Area permissions: the M01 §4 matrix in code (D-208, D-215). Each case is a Gate.
 *
 * Roles are fixed (D-208): there is no permission table. Changing a row here means changing an
 * approved specification, so it needs a decision first (CLAUDE.md §10.6).
 *
 * Entity-level rules (an agent only sees his own clients, proposals and documents, RB-03)
 * live in the Policies and in the owner scope (App\Models\Concerns\HasOwner).
 */
enum Ability: string
{
    // Every role
    case Dashboard = 'dashboard';
    case Profile = 'profile';

    // M02 Anagrafiche sportive
    case SportsView = 'sports.view';                          // D-038
    case SportsStructureManage = 'sports.structure.manage';   // seasons, competitions, clubs (D-137)
    case SportsCalendarsManage = 'sports.calendars.manage';   // calendars, import, matches, stadiums (D-137)

    // M03 Catalogo e listini
    case CatalogView = 'catalog.view';                        // D-137
    case CatalogManage = 'catalog.manage';                    // D-020

    // M04 Magazzino
    case InventoryView = 'inventory.view';                    // D-065, D-071 (the agent sees residuals only in the configurator)
    case InventoryCapacityManage = 'inventory.capacity.manage'; // D-137

    // M05 Clienti
    case ClientsOwn = 'clients.own';                          // create, edit own clients (D-076, D-078)
    case ClientsViewAll = 'clients.view_all';                 // D-082
    case ClientsManageAll = 'clients.manage_all';             // assign, merge (D-082)
    case CommoditySectorsManage = 'commodity_sectors.manage'; // D-075

    // M06, M07 Proposte e approvazione
    case ProposalsOwn = 'proposals.own';                      // create, duplicate, submit, withdraw own (D-069, D-100)
    case ProposalsViewAll = 'proposals.view_all';
    case ProposalsReview = 'proposals.review';                // edit under review, discount, approve, reject (D-068, D-098, D-104)
    case ProposalsForce = 'proposals.force';                  // beyond availability (D-070)
    case ProposalsManageApproved = 'proposals.manage_approved'; // edit approved, cancel (D-041, D-095)

    // M08 Contenuti, slide e KPI
    case ContentManage = 'content.manage';                    // D-109

    // M09 Documenti
    case DocumentsDownloadAll = 'documents.download_all';     // the agent downloads only his own approved ones (D-090)
    case DocumentsRegenerate = 'documents.regenerate';        // D-125

    // M10 Audit e report
    case AuditView = 'audit.view';                            // audit and access report (D-129)
    case ReportsView = 'reports.view';                        // R-01…R-05 (D-134)
    case PersonalDashboard = 'reports.personal';              // R-06 (D-130)

    // M01 Utenti
    case UsersManage = 'users.manage';                        // D-139

    // Settings (ux-architettura, "Navigazione per ruolo": admin only)
    case SettingsManage = 'settings.manage';

    /**
     * Roles allowed by the M01 §4 matrix.
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::Dashboard,
            self::Profile => [Role::Admin, Role::Manager, Role::Agent],

            self::ClientsOwn => [Role::Admin, Role::Agent],

            self::ProposalsOwn,
            self::PersonalDashboard => [Role::Agent],

            self::SportsView,
            self::SportsCalendarsManage,
            self::CatalogView,
            self::InventoryView,
            self::ClientsViewAll,
            self::ProposalsViewAll,
            self::ProposalsReview,
            self::ContentManage,
            self::DocumentsDownloadAll,
            self::ReportsView => [Role::Admin, Role::Manager],

            self::SportsStructureManage,
            self::CatalogManage,
            self::InventoryCapacityManage,
            self::ClientsManageAll,
            self::CommoditySectorsManage,
            self::ProposalsForce,
            self::ProposalsManageApproved,
            self::DocumentsRegenerate,
            self::AuditView,
            self::UsersManage,
            self::SettingsManage => [Role::Admin],
        };
    }

    public function allows(Role $role): bool
    {
        return in_array($role, $this->roles(), true);
    }
}
