<?php

use App\Http\Controllers\Dev\DevLoginController;
use App\Http\Controllers\PlaceholderPageController;
use App\Http\Controllers\Preview\LayoutPreviewController;
use App\Http\Middleware\EnsureDevLoginEnabled;
use App\Http\Middleware\EnsureLayoutPreviewEnabled;
use App\Support\DevLogin;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Every route is listed in tests/Feature/Authorization/RouteMatrixTest.php with the expected
| answer for guest, agent, manager, admin and deactivated user (D-215): a route missing from
| the matrix makes the test fail.
*/

// Guest page until the login arrives (PR-03). Guests asking for a protected page are sent here.
Route::get('/', fn () => Inertia::render('Home', [
    'title' => __('app.home.title'),
    'subtitle' => __('app.home.subtitle'),
    'previewUrl' => EnsureLayoutPreviewEnabled::enabled()
        ? route('preview.layout', ['role' => 'agente'])
        : null,
    'devLoginUrl' => DevLogin::enabled() ? route('dev.login') : null,
]))->name('home');

// Menu pages (PR-02, D-269): signed-in users only, each behind the Gate of its area (M01 §4).
Route::middleware('auth')->group(function (): void {
    foreach (PortalPages::PAGES as $key => [$path, $ability]) {
        Route::get($path, PlaceholderPageController::class)
            ->defaults('page', $key)
            ->middleware('can:'.$ability->value)
            ->name(PortalPages::routeName($key));
    }
});

// Development access (D-268): local machine only, removed by PR-03.
if (DevLogin::enabled()) {
    Route::middleware(EnsureDevLoginEnabled::class)
        ->prefix('sviluppo')
        ->name('dev.')
        ->group(function (): void {
            Route::get('accesso', [DevLoginController::class, 'index'])->name('login');
            Route::post('accesso/{user}', [DevLoginController::class, 'store'])->name('login.store');
            Route::post('esci', [DevLoginController::class, 'destroy'])->name('logout');
        });
}

// Layout preview (PR-01, D-264): local and testing environments only, removed by PR-03.
if (EnsureLayoutPreviewEnabled::enabled()) {
    Route::middleware(EnsureLayoutPreviewEnabled::class)
        ->prefix('anteprima')
        ->name('preview.')
        ->group(function (): void {
            Route::get('layout/{role}', [LayoutPreviewController::class, 'show'])
                ->whereIn('role', array_keys(LayoutPreviewController::ROLES))
                ->name('layout');
            Route::post('errore', [LayoutPreviewController::class, 'error'])
                ->name('error');
        });
}
