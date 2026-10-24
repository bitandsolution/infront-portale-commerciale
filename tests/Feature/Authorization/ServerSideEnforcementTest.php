<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\Navigation\NavigationMenu;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Gate;

/*
| RB-02: permissions are applied by the server, not only by hiding menu entries.
*/

it('M01-CA3: hides the admin functions from the manager menu and refuses the direct call', function (string $page) {
    $manager = User::factory()->manager()->create();

    $menu = collect(NavigationMenu::forRole($manager->role, null, fn (string $key): string => '/'.$key))
        ->flatMap(fn (array $item): array => [$item['key'], ...array_column($item['children'], 'key')]);

    expect($menu)->not->toContain($page);

    $this->actingAs($manager)->get(PortalPages::url($page))->assertForbidden();
})->with([
    'capacità di magazzino' => 'inventory_capacity',
    'stagioni' => 'sports_seasons',
    'competizioni' => 'sports_competitions',
    'club' => 'sports_clubs',
    'utenti' => 'users',
    'audit' => 'audit',
    'report accessi' => 'reports_access',
]);

it('RB-02: refuses a page outside the role even when typed in the address bar', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/catalogo/led')
        ->assertForbidden()
        ->assertSee('Non hai i permessi per questa funzione');
});

it('RB-02: during an Inertia visit answers a forbidden page with a toast on the previous page', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->from(PortalPages::url('dashboard'))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ])
        ->get(PortalPages::url('users'))
        ->assertRedirect(PortalPages::url('dashboard'))
        ->assertSessionHas('toast.message', 'Non hai i permessi per questa funzione');
});

it('D-161: never shows a menu entry whose page the role cannot open', function (string $role) {
    $user = User::factory()->state(['role' => $role])->make();

    $pages = collect(NavigationMenu::forRole($user->role, null, fn (string $key): string => '/'.$key))
        ->flatMap(fn (array $item): array => $item['children'] === [] ? [$item['key']] : array_column($item['children'], 'key'));

    foreach ($pages as $page) {
        [, $ability] = PortalPages::PAGES[$page];
        expect(Gate::forUser($user)->allows($ability->value))->toBeTrue("{$role} sees {$page} but cannot open it");
    }
})->with(['agent', 'manager', 'admin']);

it('D-269: has a protected route for every page of the menu, and no route without a menu page', function () {
    // The profile is reached from the user menu of the top bar (D-267).
    expect(array_keys(PortalPages::PAGES))
        ->toEqualCanonicalizing([...NavigationMenu::pageKeys(), ...PortalPages::USER_MENU_PAGES]);
});
