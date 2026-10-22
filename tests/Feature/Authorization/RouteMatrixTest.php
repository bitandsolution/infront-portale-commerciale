<?php

use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
| Route × role matrix (D-215, CLAUDE.md §11): every route of the application with the expected
| answer for each kind of user. Written by hand from the M01 §4 matrix, not derived from the code,
| so a wrong permission in the code makes this test fail.
|
| A new route must be added here: "every route is in the matrix" fails otherwise.
|
| Expected answers:
|   200, 403, 500 HTTP status
|   'home'        redirect to the home page: guests until the login arrives (PR-03 points it to the
|                 login) and deactivated users, whose session is closed (RB-05)
|   'dashboard'   redirect to the dashboard
|   'dev_login'   redirect to the development access page
*/

const MATRIX_ACTORS = ['guest', 'agent', 'manager', 'admin', 'inactive'];

/**
 * Framework routes outside the matrix: they are not part of the portal pages.
 */
const MATRIX_IGNORED_URIS = ['up', 'storage/{path}'];

/**
 * @return array<string, array{method: string, expected: array{int|string, int|string, int|string, int|string, int|string}}>
 */
function routeMatrix(): array
{
    // guest, agent, manager, admin, inactive
    $everyone = [200, 200, 200, 200, 'home'];
    $signedIn = ['home', 200, 200, 200, 'home'];
    $agent = ['home', 200, 403, 403, 'home'];
    $agentAndAdmin = ['home', 200, 403, 200, 'home'];
    $backOffice = ['home', 403, 200, 200, 'home'];
    $admin = ['home', 403, 403, 200, 'home'];

    return [
        'home' => ['method' => 'GET', 'expected' => $everyone],

        // Every role
        'pages.dashboard' => ['method' => 'GET', 'expected' => $signedIn],
        'pages.profile' => ['method' => 'GET', 'expected' => $signedIn],

        // M06 — the agent's own proposals (D-069)
        'pages.proposals_new' => ['method' => 'GET', 'expected' => $agent],
        'pages.proposals_mine' => ['method' => 'GET', 'expected' => $agent],
        // M07 — review and every proposal (D-068)
        'pages.proposals_pending' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.proposals_all' => ['method' => 'GET', 'expected' => $backOffice],

        // M05 — own clients: agent and admin (D-076, D-078); all clients: manager and admin (D-082)
        'pages.clients_mine' => ['method' => 'GET', 'expected' => $agentAndAdmin],
        'pages.clients' => ['method' => 'GET', 'expected' => $backOffice],

        // M03 — consultation (D-137)
        'pages.catalog_led' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.catalog_partnership' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.catalog_hospitality' => ['method' => 'GET', 'expected' => $backOffice],

        // M04 — consultation (D-137); capacities only admin (D-137, M01-CA3)
        'pages.inventory_availability' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.inventory_capacity' => ['method' => 'GET', 'expected' => $admin],

        // M08 (D-109)
        'pages.content_slides' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.content_simulator' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.content_kpi' => ['method' => 'GET', 'expected' => $backOffice],

        // M02 — calendars, import, matches, stadiums: manager and admin (D-137)
        'pages.sports_calendars' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.sports_import' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.sports_matches' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.sports_stadiums' => ['method' => 'GET', 'expected' => $backOffice],
        // M02 — seasons, competitions, clubs: admin only (D-137, M01-CA3)
        'pages.sports_seasons' => ['method' => 'GET', 'expected' => $admin],
        'pages.sports_competitions' => ['method' => 'GET', 'expected' => $admin],
        'pages.sports_clubs' => ['method' => 'GET', 'expected' => $admin],

        // M10 — reports R-01…R-05 (D-134); access report and audit admin only (D-129)
        'pages.reports_proposals' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_agents' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_clubs' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_inventory' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_sectors' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_access' => ['method' => 'GET', 'expected' => $admin],
        'pages.audit' => ['method' => 'GET', 'expected' => $admin],

        // M01 — users admin only (D-139); settings admin only
        'pages.users' => ['method' => 'GET', 'expected' => $admin],
        'pages.settings' => ['method' => 'GET', 'expected' => $admin],

        // Development access (D-268): local and testing environments only
        'dev.login' => ['method' => 'GET', 'expected' => $everyone],
        'dev.login.store' => ['method' => 'POST', 'expected' => ['dashboard', 'dashboard', 'dashboard', 'dashboard', 'home']],
        'dev.logout' => ['method' => 'POST', 'expected' => ['dev_login', 'dev_login', 'dev_login', 'dev_login', 'home']],

        // Layout preview (D-264): local and testing environments only
        'preview.layout' => ['method' => 'GET', 'expected' => $everyone],
        'preview.error' => ['method' => 'POST', 'expected' => [500, 500, 500, 500, 'home']],
    ];
}

function matrixActor(string $actor): ?User
{
    return match ($actor) {
        'guest' => null,
        'agent' => User::factory()->agent()->create(),
        'manager' => User::factory()->manager()->create(),
        'admin' => User::factory()->admin()->create(),
        'inactive' => User::factory()->admin()->inactive()->create(),
        default => throw new InvalidArgumentException($actor),
    };
}

/**
 * @return array<string, mixed>
 */
function matrixParameters(string $route): array
{
    return match ($route) {
        'dev.login.store' => ['user' => User::factory()->agent()->create()],
        'preview.layout' => ['role' => 'admin'],
        default => [],
    };
}

dataset('route matrix', function (): Generator {
    foreach (routeMatrix() as $route => $row) {
        foreach (MATRIX_ACTORS as $index => $actor) {
            yield "{$route} × {$actor}" => [$route, $row['method'], $actor, $row['expected'][$index]];
        }
    }
});

it('D-215: answers each route as the M01 §4 matrix expects', function (string $route, string $method, string $actor, int|string $expected) {
    $user = matrixActor($actor);
    if ($user !== null) {
        $this->actingAs($user);
    }

    $response = $this->call($method, route($route, matrixParameters($route)));

    match ($expected) {
        'home' => $response->assertRedirect(route('home')),
        'dashboard' => $response->assertRedirect(route('pages.dashboard')),
        'dev_login' => $response->assertRedirect(route('dev.login')),
        default => $response->assertStatus($expected),
    };
})->with('route matrix');

it('D-215: every route is in the matrix', function () {
    $missing = collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route): bool => in_array($route->uri(), MATRIX_IGNORED_URIS, true))
        ->map(fn (RouteDefinition $route): string => $route->getName() ?? $route->uri())
        ->reject(fn (string $name): bool => array_key_exists($name, routeMatrix()))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Routes missing from the matrix: '.implode(', ', $missing));
});

it('D-215: the matrix has no stale rows', function () {
    $names = collect(Route::getRoutes()->getRoutes())->map(fn (RouteDefinition $route): ?string => $route->getName());

    foreach (array_keys(routeMatrix()) as $route) {
        expect($names->contains($route))->toBeTrue("Route in the matrix but not in the application: {$route}");
    }
});
