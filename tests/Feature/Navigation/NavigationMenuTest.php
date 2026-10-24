<?php

use App\Enums\Role;
use App\Support\Navigation\NavigationMenu;

/**
 * Side menu by role (ux-architettura "Navigazione per ruolo", D-161).
 *
 * @return list<string> top-level keys, with children as "parent>child"
 */
function menuKeys(Role $role): array
{
    $keys = [];
    foreach (NavigationMenu::forRole($role, null, fn (string $key): string => '/'.$key) as $item) {
        $keys[] = $item['key'];
        foreach ($item['children'] as $child) {
            $keys[] = $item['key'].'>'.$child['key'];
        }
    }

    return $keys;
}

it('D-161, D-267: shows the agent only Dashboard, Nuova proposta, Le mie proposte, I miei clienti', function () {
    expect(menuKeys(Role::Agent))->toBe([
        'dashboard',
        'proposals_new',
        'proposals_mine',
        'clients_mine',
    ]);
});

it('D-267: no role has "Profilo" in the side menu: it is in the user menu of the top bar', function (Role $role) {
    expect(menuKeys($role))->not->toContain('profile');
})->with(Role::cases());

it('D-161: hides the admin-only entries from the manager', function () {
    $manager = menuKeys(Role::Manager);

    expect($manager)
        ->toContain('dashboard', 'proposals>proposals_pending', 'proposals>proposals_all', 'clients',
            'catalog>catalog_led', 'inventory>inventory_availability', 'content>content_slides',
            'sports>sports_calendars', 'sports>sports_import', 'sports>sports_matches', 'sports>sports_stadiums',
            'reports>reports_proposals', 'reports>reports_sectors')
        ->not->toContain('inventory>inventory_capacity', 'sports>sports_seasons', 'sports>sports_competitions',
            'sports>sports_clubs', 'reports>reports_access', 'audit', 'users', 'settings',
            'proposals_new', 'proposals_mine', 'clients_mine', 'profile');
});

it('D-161: gives the admin every back-office entry', function () {
    $admin = menuKeys(Role::Admin);

    expect($admin)
        ->toContain('inventory>inventory_capacity', 'sports>sports_seasons', 'sports>sports_competitions',
            'sports>sports_clubs', 'reports>reports_access', 'audit', 'users', 'settings')
        ->and(array_diff(menuKeys(Role::Manager), $admin))->toBe([]);
});

it('does not offer "Proposte › Storico" in release 1.0 (D-227)', function () {
    expect(NavigationMenu::pageKeys())->not->toContain('proposals_history');
});

it('marks the active page and its group', function () {
    $menu = collect(NavigationMenu::forRole(Role::Admin, 'catalog_led', fn (string $key): string => '/'.$key));

    $catalog = $menu->firstWhere('key', 'catalog');

    expect($catalog['active'])->toBeTrue()
        ->and($catalog['href'])->toBeNull()
        ->and(collect($catalog['children'])->firstWhere('key', 'catalog_led')['active'])->toBeTrue()
        ->and($menu->firstWhere('key', 'dashboard')['active'])->toBeFalse();
});

it('has an Italian label in lang/it/ui.php for every entry', function () {
    foreach (NavigationMenu::definition() as $item) {
        foreach ([$item, ...$item->children] as $entry) {
            expect(trans()->has('ui.menu.'.$entry->key))->toBeTrue("Missing label: ui.menu.{$entry->key}");
        }
    }
});
