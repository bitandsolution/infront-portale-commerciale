<?php

namespace App\Support\Navigation;

use App\Enums\Role;
use Closure;

/**
 * Side menu by role (ux-architettura, "Navigazione per ruolo"; D-161).
 *
 * Single definition, filtered on the server: entries a role cannot use are not sent to the browser.
 * "Proposte › Storico" is not used in release 1.0 (no migration, D-227).
 */
final class NavigationMenu
{
    /**
     * @return list<NavigationItem>
     */
    public static function definition(): array
    {
        $agent = [Role::Agent];
        $backOffice = [Role::Admin, Role::Manager];
        $admin = [Role::Admin];

        return [
            new NavigationItem('dashboard', 'House', [Role::Agent, Role::Manager, Role::Admin]),

            // Agent
            new NavigationItem('proposals_new', 'Plus', $agent),
            new NavigationItem('proposals_mine', 'FileText', $agent),
            new NavigationItem('clients_mine', 'Users', $agent),
            new NavigationItem('profile', 'UserRound', $agent),

            // Manager and admin
            new NavigationItem('proposals', 'FileText', $backOffice, [
                new NavigationItem('proposals_pending', 'FileText', $backOffice),
                new NavigationItem('proposals_all', 'FileText', $backOffice),
            ]),
            new NavigationItem('clients', 'Users', $backOffice),
            new NavigationItem('catalog', 'Tags', $backOffice, [
                new NavigationItem('catalog_led', 'Tags', $backOffice),
                new NavigationItem('catalog_partnership', 'Tags', $backOffice),
                new NavigationItem('catalog_hospitality', 'Tags', $backOffice),
            ]),
            new NavigationItem('inventory', 'Warehouse', $backOffice, [
                new NavigationItem('inventory_availability', 'Warehouse', $backOffice),
                new NavigationItem('inventory_capacity', 'Warehouse', $admin),
            ]),
            new NavigationItem('content', 'Images', $backOffice, [
                new NavigationItem('content_slides', 'Images', $backOffice),
                new NavigationItem('content_simulator', 'Images', $backOffice),
                new NavigationItem('content_kpi', 'Images', $backOffice),
            ]),
            new NavigationItem('sports', 'Trophy', $backOffice, [
                new NavigationItem('sports_calendars', 'Trophy', $backOffice),
                new NavigationItem('sports_import', 'Trophy', $backOffice),
                new NavigationItem('sports_matches', 'Trophy', $backOffice),
                new NavigationItem('sports_stadiums', 'Trophy', $backOffice),
                new NavigationItem('sports_seasons', 'Trophy', $admin),
                new NavigationItem('sports_competitions', 'Trophy', $admin),
                new NavigationItem('sports_clubs', 'Trophy', $admin),
            ]),
            new NavigationItem('reports', 'ChartColumn', $backOffice, [
                new NavigationItem('reports_proposals', 'ChartColumn', $backOffice),
                new NavigationItem('reports_agents', 'ChartColumn', $backOffice),
                new NavigationItem('reports_clubs', 'ChartColumn', $backOffice),
                new NavigationItem('reports_inventory', 'ChartColumn', $backOffice),
                new NavigationItem('reports_sectors', 'ChartColumn', $backOffice),
                new NavigationItem('reports_access', 'ChartColumn', $admin),
            ]),
            new NavigationItem('audit', 'ScrollText', $admin),
            new NavigationItem('users', 'UserCog', $admin),
            new NavigationItem('settings', 'Settings', $admin),
        ];
    }

    /**
     * Every page key (leaf entry), whatever the role.
     *
     * @return list<string>
     */
    public static function pageKeys(): array
    {
        $keys = [];
        foreach (self::definition() as $item) {
            foreach ($item->isGroup() ? $item->children : [$item] as $page) {
                $keys[] = $page->key;
            }
        }

        return $keys;
    }

    /**
     * Menu for a role, ready for the frontend.
     *
     * @param  Closure(string): string  $href  page key → URL
     * @return list<array{key: string, label: string, icon: string, href: string|null, active: bool, children: list<array{key: string, label: string, href: string, active: bool}>}>
     */
    public static function forRole(Role $role, ?string $activeKey, Closure $href): array
    {
        $menu = [];

        foreach (self::definition() as $item) {
            if (! $item->isVisibleTo($role)) {
                continue;
            }

            $children = [];
            foreach ($item->children as $child) {
                if ($child->isVisibleTo($role)) {
                    $children[] = [
                        'key' => $child->key,
                        'label' => self::label($child->key),
                        'href' => $href($child->key),
                        'active' => $child->key === $activeKey,
                    ];
                }
            }

            $menu[] = [
                'key' => $item->key,
                'label' => self::label($item->key),
                'icon' => $item->icon,
                'href' => $item->isGroup() ? null : $href($item->key),
                'active' => $item->key === $activeKey
                    || in_array($activeKey, array_column($children, 'key'), true),
                'children' => $children,
            ];
        }

        return $menu;
    }

    public static function label(string $key): string
    {
        $label = __('ui.menu.'.$key);

        return is_string($label) ? $label : $key;
    }
}
