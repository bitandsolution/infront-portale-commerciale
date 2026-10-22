<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\SupportCode;
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
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // D-265: every reported error carries the support code shown to the user.
        $exceptions->context(fn (): array => [
            'support_code' => app(SupportCode::class)->value(),
        ]);

        // System errors during an Inertia visit: back to the previous page with a toast
        // and the support code (ux-architettura §4.5, "Errore di sistema").
        // Full page loads keep the error page (resources/views/errors/500.blade.php).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($response->getStatusCode() !== 500 || ! $request->header('X-Inertia')) {
                return $response;
            }

            return back()->with('toast', [
                'id' => (string) Str::uuid(),
                'type' => 'error',
                'message' => __('ui.errors.system'),
                'code' => app(SupportCode::class)->value(),
            ]);
        });
    })->create();
