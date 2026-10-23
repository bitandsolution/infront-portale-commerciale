<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\PlaceholderPageController;
use App\Http\Middleware\EnsureTwoFactorIsConfigured;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
| Every route is listed in tests/Feature/Authorization/RouteMatrixTest.php with the expected
| answer for guest, agent, manager, admin, deactivated user and user without the second factor
| (D-215): a route missing from the matrix makes the test fail.
|
| Fortify's own routes are not registered (App\Providers\FortifyServiceProvider): the portal
| declares here the few it uses, with Fortify's controllers.
*/

// Login (D-214): e-mail and password, then the code of the second factor at every login.
Route::middleware('guest')->group(function (): void {
    Route::get('accesso', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('accesso', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('accesso/verifica', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('accesso/verifica', [TwoFactorChallengeController::class, 'store'])->name('two-factor.login.store');
});

// Signed-in users. A password change closes the other sessions of the user (auth.session, D-214).
Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::post('esci', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Mandatory setup of the second factor (D-136, M01-CA1), recovery codes shown once.
    Route::get('verifica-in-due-passaggi', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
    Route::post('verifica-in-due-passaggi', [TwoFactorSetupController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('two-factor.confirm');
    Route::get('verifica-in-due-passaggi/codici-di-recupero', [TwoFactorSetupController::class, 'recoveryCodes'])
        ->name('two-factor.recovery-codes');

    // Portal pages: only with the second factor configured.
    Route::middleware(EnsureTwoFactorIsConfigured::class)->group(function (): void {
        Route::redirect('/', PortalPages::PAGES['dashboard'][0])->name('home');

        // Menu pages (PR-02, D-269), each behind the Gate of its area (M01 §4).
        foreach (PortalPages::PAGES as $key => [$path, $ability]) {
            Route::get($path, PlaceholderPageController::class)
                ->defaults('page', $key)
                ->middleware('can:'.$ability->value)
                ->name(PortalPages::routeName($key));
        }
    });
});
