<?php

use App\Enums\AliasSource;
use App\Enums\FilePurpose;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
|   'allowed'     neither a redirect nor 401/403: the permission lets the request through (Horizon's
|                 API calls with an unknown id or an empty body, whose own answer is not the point;
|                 /health, whose 200 or 503 depends on the state of the services)
*/

const MATRIX_ACTORS = ['guest', 'agent', 'manager', 'admin', 'inactive', 'no_2fa'];

/**
 * Framework routes outside the matrix: none. Laravel's /up was replaced by /health (PR-09), which is
 * in the matrix. The private disk is never published at storage/{path} (PR-06, D-218): that route
 * must not exist at all.
 */
const MATRIX_IGNORED_URIS = [];

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
    $backOfficeForm = ['login', 403, 302, 302, 'login', 'setup'];
    $adminAllowed = ['login', 403, 403, 'allowed', 'login', 'setup'];

    return [
        // Internal health (PR-09, D-179): public for the uptime service, same answer for everybody
        // (200 or 503 depending on the state, never a redirect; HealthCheckTest)
        'health' => ['method' => 'GET', 'expected' => ['allowed', 'allowed', 'allowed', 'allowed', 'allowed', 'allowed']],

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

        // Self-service reset of the password (D-279; PR-08): guests only, like the login. An empty
        // form goes back with the errors.
        'password.request' => ['method' => 'GET', 'expected' => [200, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],
        'password.email' => ['method' => 'POST', 'expected' => [302, 'dashboard', 'dashboard', 'dashboard', 'login', 'dashboard']],

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

        // M02 — seasons, competitions and their editions: admin only (D-137, D-321). Forms are sent
        // empty; the removal of an empty edition goes back to the page with a toast
        'sports.seasons.create' => ['method' => 'GET', 'expected' => $admin],
        'sports.seasons.store' => ['method' => 'POST', 'expected' => $adminForm],
        'sports.seasons.edit' => ['method' => 'GET', 'expected' => $admin],
        'sports.seasons.update' => ['method' => 'PUT', 'expected' => $adminForm],
        'sports.editions.store' => ['method' => 'POST', 'expected' => $adminForm],
        'sports.editions.update' => ['method' => 'PUT', 'expected' => $adminForm],
        'sports.editions.destroy' => ['method' => 'DELETE', 'expected' => $adminForm],
        'sports.editions.show' => ['method' => 'GET', 'expected' => $admin],
        'sports.enrollments.store' => ['method' => 'POST', 'expected' => $adminForm],
        'sports.enrollments.destroy' => ['method' => 'DELETE', 'expected' => $adminForm],
        'sports.competitions.create' => ['method' => 'GET', 'expected' => $admin],
        'sports.competitions.store' => ['method' => 'POST', 'expected' => $adminForm],
        'sports.competitions.edit' => ['method' => 'GET', 'expected' => $admin],
        'sports.competitions.update' => ['method' => 'PUT', 'expected' => $adminForm],

        // M02 — clubs with logo and aliases: admin only (D-137); stadiums: manager and admin (D-137).
        // Forms and uploads are sent empty; the removal of an alias goes back with a toast
        'sports.clubs.create' => ['method' => 'GET', 'expected' => $admin],
        'sports.clubs.store' => ['method' => 'POST', 'expected' => $adminForm],
        'sports.clubs.edit' => ['method' => 'GET', 'expected' => $admin],
        'sports.clubs.update' => ['method' => 'PUT', 'expected' => $adminForm],
        'sports.clubs.logo' => ['method' => 'POST', 'expected' => $adminForm],
        'sports.clubs.aliases.store' => ['method' => 'POST', 'expected' => $adminForm],
        'sports.clubs.aliases.destroy' => ['method' => 'DELETE', 'expected' => $adminForm],
        'sports.stadiums.create' => ['method' => 'GET', 'expected' => $backOffice],
        'sports.stadiums.store' => ['method' => 'POST', 'expected' => $backOfficeForm],
        'sports.stadiums.edit' => ['method' => 'GET', 'expected' => $backOffice],
        'sports.stadiums.update' => ['method' => 'PUT', 'expected' => $backOfficeForm],

        // M02 — matches: manager and admin (D-137). Without a season the new-match page goes back to
        // the list; forms are sent empty; cancel and restore go back with a toast
        'sports.matches.create' => ['method' => 'GET', 'expected' => $backOfficeForm],
        'sports.matches.store' => ['method' => 'POST', 'expected' => $backOfficeForm],
        'sports.matches.edit' => ['method' => 'GET', 'expected' => $backOffice],
        'sports.matches.update' => ['method' => 'PUT', 'expected' => $backOfficeForm],
        'sports.matches.cancel' => ['method' => 'POST', 'expected' => $backOfficeForm],
        'sports.matches.restore' => ['method' => 'POST', 'expected' => $backOfficeForm],

        // Season of the top bar (D-317): every role; sent empty, back with the errors
        'season.select' => ['method' => 'POST', 'expected' => $signedInForm],

        // M10 — reports R-01…R-05 (D-134); access report and audit admin only (D-129)
        'pages.reports_proposals' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_agents' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_clubs' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_inventory' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_sectors' => ['method' => 'GET', 'expected' => $backOffice],
        'pages.reports_access' => ['method' => 'GET', 'expected' => $admin],
        'pages.audit' => ['method' => 'GET', 'expected' => $admin],

        // M01 — users admin only (D-139, M01-CA3); settings admin only (PR-07): the page and its
        // form, sent empty
        'pages.users' => ['method' => 'GET', 'expected' => $admin],
        'pages.settings' => ['method' => 'GET', 'expected' => $admin],
        'settings.update' => ['method' => 'PUT', 'expected' => $adminForm],

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

        // Stored files (D-218): until the slices that use them, only the admin (StoredFilePolicy).
        // Target: a client logo stored by the system
        'files.download' => ['method' => 'GET', 'expected' => $admin],

        // Queues panel (Horizon, progettazione-tecnica §5.2.7; PR-08): admin only, with the rules of
        // the portal pages (config/horizon.php). The page and the API calls without parameters
        // answer 200; the calls with an id or a body only need to be let through.
        'horizon.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.stats.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.workload.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.masters.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.monitoring.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.monitoring.store' => ['method' => 'POST', 'expected' => $adminAllowed],
        'horizon.monitoring-tag.paginate' => ['method' => 'GET', 'expected' => $adminAllowed],
        'horizon.monitoring-tag.destroy' => ['method' => 'DELETE', 'expected' => $adminAllowed],
        'horizon.jobs-metrics.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.jobs-metrics.show' => ['method' => 'GET', 'expected' => $adminAllowed],
        'horizon.queues-metrics.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.queues-metrics.show' => ['method' => 'GET', 'expected' => $adminAllowed],
        'horizon.jobs-batches.index' => ['method' => 'GET', 'expected' => $adminAllowed],
        'horizon.jobs-batches.show' => ['method' => 'GET', 'expected' => $adminAllowed],
        'horizon.jobs-batches.retry' => ['method' => 'POST', 'expected' => $adminAllowed],
        'horizon.pending-jobs.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.completed-jobs.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.silenced-jobs.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.failed-jobs.index' => ['method' => 'GET', 'expected' => $admin],
        'horizon.failed-jobs.show' => ['method' => 'GET', 'expected' => $adminAllowed],
        'horizon.retry-jobs.show' => ['method' => 'POST', 'expected' => $adminAllowed],
        'horizon.jobs.show' => ['method' => 'GET', 'expected' => $adminAllowed],
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
        in_array($route, ['sports.seasons.edit', 'sports.seasons.update', 'sports.editions.store'], true) => [
            'season' => Season::factory()->create()->id,
        ],
        $route === 'sports.enrollments.destroy' => [
            'clubEnrollment' => ClubEnrollment::query()->create(['competition_season_id' => CompetitionSeason::factory()->create()->id, 'club_id' => Club::factory()->create()->id])->id,
        ],
        in_array($route, ['sports.matches.edit', 'sports.matches.update', 'sports.matches.cancel', 'sports.matches.restore'], true) => [
            'fixtureMatch' => FixtureMatch::factory()->create()->id,
        ],
        in_array($route, ['sports.editions.update', 'sports.editions.destroy', 'sports.editions.show', 'sports.enrollments.store'], true) => [
            'competitionSeason' => CompetitionSeason::factory()->create()->id,
        ],
        in_array($route, ['sports.clubs.edit', 'sports.clubs.update', 'sports.clubs.logo', 'sports.clubs.aliases.store'], true) => [
            'club' => Club::factory()->create()->id,
        ],
        $route === 'sports.clubs.aliases.destroy' => [
            'clubAlias' => ClubAlias::query()->create(['club_id' => ($club = Club::factory()->create())->id, 'discipline_id' => $club->discipline_id, 'alias' => 'Alias matrice', 'source' => AliasSource::Manual])->id,
        ],
        in_array($route, ['sports.stadiums.edit', 'sports.stadiums.update'], true) => [
            'stadium' => Stadium::factory()->create()->id,
        ],
        in_array($route, ['sports.competitions.edit', 'sports.competitions.update'], true) => [
            'competition' => Competition::factory()->create()->id,
        ],
        $route === 'files.download' => ['storedFile' => storeTestFile(FilePurpose::ClientLogo)->id],
        str_starts_with($route, 'horizon.monitoring-tag.') => ['tag' => 'matrice'],
        str_starts_with($route, 'horizon.') && str_ends_with($route, '.show'),
        $route === 'horizon.jobs-batches.retry' => ['id' => 'matrice'],
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
    Storage::fake(StoredFile::DISK);
    $user = matrixActor($actor);
    if ($user !== null) {
        $this->actingAs($user);
    }

    $response = $this->call($method, route($route, matrixParameters($route)));

    match ($expected) {
        'login' => $response->assertRedirect(route('login')),
        'dashboard' => $response->assertRedirect(route('pages.dashboard')),
        'setup' => $response->assertRedirect(route('two-factor.setup')),
        'allowed' => expect($response->isRedirect() || in_array($response->getStatusCode(), [401, 403], true))
            ->toBeFalse("{$route} × {$actor}: answered {$response->getStatusCode()}"),
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
