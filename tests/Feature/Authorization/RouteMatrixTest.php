<?php

use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
| Route × role matrix (D-215, CLAUDE.md §11): every route of the application with the expected
| answer for each kind of user. Written by hand from the M01 §4 matrix, not derived from the code,
| so a wrong permission in the code makes this test fail.
|
| A new route must be added here: "every route is in the matrix" fails otherwise.
|
| Actors: guest; agent, manager and admin with the second factor configured; a deactivated
| admin (RB-05); an active agent who has not configured the second factor yet (M01-CA1).
|
| Expected answers:
|   200, 403, 302 HTTP status (302: back to the form, e.g. validation of an empty submission)
|   'login'       redirect to the login: guests (D-269), deactivated users, whose session is
|                 closed (RB-05), and every logout
|   'dashboard'   redirect to the dashboard
|   'setup'       redirect to the mandatory setup of the second factor (D-136, M01-CA1)
*/

const MATRIX_ACTORS = ['guest', 'agent', 'manager', 'admin', 'inactive', 'no_2fa'];

/**
 * Framework routes outside the matrix: they are not part of the portal pages.
 */
const MATRIX_IGNORED_URIS = ['up', 'storage/{path}'];

/**
 * @return array<string, array{method: string, expected: array{int|string, int|string, int|string, int|string, int|string, int|string}}>
 */
function routeMatrix(): array
{
    // guest, agent, manager, admin, inactive, no_2fa
    $signedIn = ['login', 200, 200, 200, 'login', 'setup'];
    $agent = ['login', 200, 403, 403, 'login', 'setup'];
    $agentAndAdmin = ['login', 200, 403, 200, 'login', 'setup'];
    $backOffice = ['login', 403, 200, 200, 'login', 'setup'];
    $admin = ['login', 403, 403, 200, 'login', 'setup'];
    $signedInForm = ['login', 302, 302, 302, 'login', 'setup'];
    $adminForm = ['login', 403, 403, 302, 'login', 'setup'];

    return [
        'home' => ['method' => 'GET', 'expected' => ['login', 'dashboard', 'dashboard', 'dashboard', 'login', 'setup']],

        // Login (D-214): guests only; signed-in users go to their dashboard
        'login' => ['method' => 'GET', 'expected' => [200, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        'login.store' => ['method' => 'POST', 'expected' => [302, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        // Second step: without a pending login, back to the login
        'two-factor.login' => ['method' => 'GET', 'expected' => ['login', 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        'two-factor.login.store' => ['method' => 'POST', 'expected' => ['login', 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        'logout' => ['method' => 'POST', 'expected' => ['login', 'login', 'login', 'login', 'login', 'login']],

        // Access links sent by e-mail (D-277…D-280): guests only, like the login. With an unknown
        // token the page says the link is not valid; an empty form goes back with the errors.
        'access.activation' => ['method' => 'GET', 'expected' => [200, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        'access.activation.store' => ['method' => 'POST', 'expected' => [302, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        'access.reset' => ['method' => 'GET', 'expected' => [200, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        'access.reset.store' => ['method' => 'POST', 'expected' => [302, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],

        // Mandatory setup of the second factor (D-136): only who has not configured it yet
        'two-factor.setup' => ['method' => 'GET', 'expected' => ['login', 'dashboard', 'dashboard', 'dashboard', 'login', 200]],
        'two-factor.confirm' => ['method' => 'POST', 'expected' => ['login', 'dashboard', 'dashboard', 'dashboard', 'login', 302]],
        // Recovery codes: only once, right after the confirmation (TwoFactorSetupTest)
        'two-factor.recovery-codes' => ['method' => 'GET', 'expected' => ['login', 'dashboard', 'dashboard', 'dashboard', 'login', 'setup']],

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

        // M01 — users admin only (D-139, M01-CA3); settings admin only
        'pages.users' => ['method' => 'GET', 'expected' => $admin],
        'pages.settings' => ['method' => 'GET', 'expected' => $admin],

        // M01 — personal profile of every role (D-267): an empty form goes back with the errors
        'profile.update' => ['method' => 'PUT', 'expected' => $signedInForm],
        'profile.password' => ['method' => 'PUT', 'expected' => $signedInForm],

        // M01 — user management, admin only (D-139, M01-CA3). Target: an active agent with the
        // second factor; forms are sent empty, actions go back to the page with a toast
        'users.create' => ['method' => 'GET', 'expected' => $admin],
        'users.store' => ['method' => 'POST', 'expected' => $adminForm],
        'users.show' => ['method' => 'GET', 'expected' => $admin],
        'users.update' => ['method' => 'PUT', 'expected' => $adminForm],
        'users.deactivate' => ['method' => 'POST', 'expected' => $adminForm],
        'users.reactivate' => ['method' => 'POST', 'expected' => $adminForm],
        'users.reset-password' => ['method' => 'POST', 'expected' => $adminForm],
        'users.reset-two-factor' => ['method' => 'POST', 'expected' => $adminForm],
        'users.resend-link' => ['method' => 'POST', 'expected' => $adminForm],
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
        'no_2fa' => User::factory()->agent()->withoutTwoFactor()->create(),
        default => throw new InvalidArgumentException($actor),
    };
}

/**
 * @return array<string, mixed>
 */
function matrixParameters(string $route): array
{
    return match (true) {
        in_array($route, ['users.show', 'users.update', 'users.deactivate', 'users.reactivate',
            'users.reset-password', 'users.reset-two-factor', 'users.resend-link'], true) => [
                'user' => User::factory()->agent()->create()->id,
            ],
        str_starts_with($route, 'access.') => ['token' => str_repeat('x', 64)],
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
    Mail::fake();
    $user = matrixActor($actor);
    if ($user !== null) {
        $this->actingAs($user);
    }

    $response = $this->call($method, route($route, matrixParameters($route)));

    match ($expected) {
        'login' => $response->assertRedirect(route('login')),
        'dashboard' => $response->assertRedirect(route('pages.dashboard')),
        'setup' => $response->assertRedirect(route('two-factor.setup')),
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
