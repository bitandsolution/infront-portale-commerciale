<?php

use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsureTwoFactorIsUnchanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Navigation\PortalPages;
use App\Support\SupportCode;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserIsActive::class,
            EnforceIdleTimeout::class,
            EnsureTwoFactorIsUnchanged::class,
            HandleInertiaRequests::class,
        ]);

        // A deactivated user, a session idle beyond the limit of the role (D-214) or opened with a
        // second factor reset in the meantime (D-280) is signed out before any authentication or
        // permission check runs.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureUserIsActive::class,
        );
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnforceIdleTimeout::class,
        );
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureTwoFactorIsUnchanged::class,
        );

        // Guests asking for a protected page go to the login (D-269) and come back to it afterwards;
        // signed-in users asking for the login go to their dashboard.
        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (): string => PortalPages::url('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // D-265: every reported error carries the support code shown to the user.
        $exceptions->context(fn (): array => [
            'support_code' => app(SupportCode::class)->value(),
        ]);

        // During an Inertia visit, back to the previous page with a toast (ux-architettura §4.5):
        // - 500, system error, with the support code (D-265);
        // - 403, forbidden action (RB-02);
        // - 429, too many requests (PR-08).
        // Full page loads keep the error pages (resources/views/errors/500.blade.php, 403.blade.php).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if (! $request->header('X-Inertia')) {
                return $response;
            }

            return match ($response->getStatusCode()) {
                403 => back()->with('toast', [
                    'id' => (string) Str::uuid(),
                    'type' => 'error',
                    'message' => __('ui.errors.forbidden'),
                ]),
                500 => back()->with('toast', [
                    'id' => (string) Str::uuid(),
                    'type' => 'error',
                    'message' => __('ui.errors.system'),
                    'code' => app(SupportCode::class)->value(),
                ]),
                // 429, too many requests from the same address (e.g. "Password dimenticata?", PR-08)
                429 => back()->with('toast', [
                    'id' => (string) Str::uuid(),
                    'type' => 'error',
                    'message' => __('ui.errors.too_many_requests'),
                ]),
                default => $response,
            };
        });
    })->create();
