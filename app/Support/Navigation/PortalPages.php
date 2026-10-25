<?php

namespace App\Support\Navigation;

use App\Enums\Ability;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Sports\CalendarController;
use App\Http\Controllers\Sports\CalendarImportController;
use App\Http\Controllers\Sports\ClubController;
use App\Http\Controllers\Sports\CompetitionController;
use App\Http\Controllers\Sports\MatchController;
use App\Http\Controllers\Sports\SeasonController;
use App\Http\Controllers\Sports\StadiumController;
use App\Http\Controllers\Users\ProfileController;
use App\Http\Controllers\Users\UserController;

/**
 * One route per page of the side menu, protected by its area Gate (PR-02, D-269).
 *
 * Until each function arrives the route shows the "coming soon" empty state; the slice that
 * builds the page replaces only the controller (CONTROLLERS) and keeps the URL, the Gate and its
 * row in the route × role matrix test (D-215).
 *
 * "profile" is not in the side menu: every role reaches it from the user menu of the top bar (D-267).
 */
final class PortalPages
{
    /**
     * Page key (NavigationMenu) → [path, area permission].
     *
     * @var array<string, array{string, Ability}>
     */
    public const PAGES = [
        'dashboard' => ['dashboard', Ability::Dashboard],
        'profile' => ['profilo', Ability::Profile],

        'proposals_new' => ['proposte/nuova', Ability::ProposalsOwn],
        'proposals_mine' => ['proposte/mie', Ability::ProposalsOwn],
        'proposals_pending' => ['proposte/da-approvare', Ability::ProposalsReview],
        'proposals_all' => ['proposte', Ability::ProposalsViewAll],

        'clients_mine' => ['clienti/miei', Ability::ClientsOwn],
        'clients' => ['clienti', Ability::ClientsViewAll],

        'catalog_led' => ['catalogo/led', Ability::CatalogView],
        'catalog_partnership' => ['catalogo/partnership', Ability::CatalogView],
        'catalog_hospitality' => ['catalogo/hospitality', Ability::CatalogView],

        'inventory_availability' => ['magazzino/disponibilita', Ability::InventoryView],
        'inventory_capacity' => ['magazzino/capacita', Ability::InventoryCapacityManage],

        'content_slides' => ['contenuti/slide', Ability::ContentManage],
        'content_simulator' => ['contenuti/simulatore', Ability::ContentManage],
        'content_kpi' => ['contenuti/kpi', Ability::ContentManage],

        'sports_calendars' => ['anagrafiche/calendari', Ability::SportsCalendarsManage],
        'sports_import' => ['anagrafiche/import', Ability::SportsCalendarsManage],
        'sports_matches' => ['anagrafiche/partite', Ability::SportsCalendarsManage],
        'sports_stadiums' => ['anagrafiche/stadi', Ability::SportsCalendarsManage],
        'sports_seasons' => ['anagrafiche/stagioni', Ability::SportsStructureManage],
        'sports_competitions' => ['anagrafiche/competizioni', Ability::SportsStructureManage],
        'sports_clubs' => ['anagrafiche/club', Ability::SportsStructureManage],

        'reports_proposals' => ['report/proposte', Ability::ReportsView],
        'reports_agents' => ['report/agenti', Ability::ReportsView],
        'reports_clubs' => ['report/club', Ability::ReportsView],
        'reports_inventory' => ['report/magazzino', Ability::ReportsView],
        'reports_sectors' => ['report/settori', Ability::ReportsView],
        'reports_access' => ['report/accessi', Ability::AuditView],

        'audit' => ['audit', Ability::AuditView],
        'users' => ['utenti', Ability::UsersManage],
        'settings' => ['impostazioni', Ability::SettingsManage],
    ];

    /**
     * Pages already built: page key → controller action. The others show "Funzione in arrivo".
     *
     * @var array<string, array{class-string, string}>
     */
    public const CONTROLLERS = [
        'profile' => [ProfileController::class, 'show'],
        'users' => [UserController::class, 'index'],
        'settings' => [SettingsController::class, 'index'],
        'sports_seasons' => [SeasonController::class, 'index'],
        'sports_competitions' => [CompetitionController::class, 'index'],
        'sports_clubs' => [ClubController::class, 'index'],
        'sports_stadiums' => [StadiumController::class, 'index'],
        'sports_calendars' => [CalendarController::class, 'index'],
        'sports_import' => [CalendarImportController::class, 'index'],
        'sports_matches' => [MatchController::class, 'index'],
    ];

    /**
     * Pages reached from the user menu of the top bar instead of the side menu (D-267).
     *
     * @var list<string>
     */
    public const USER_MENU_PAGES = ['profile'];

    public static function routeName(string $key): string
    {
        return 'pages.'.$key;
    }

    public static function url(string $key): string
    {
        return route(self::routeName($key));
    }
}
