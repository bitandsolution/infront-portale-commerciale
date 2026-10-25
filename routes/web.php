<?php

use App\Enums\Ability;
use App\Http\Controllers\Auth\AccessLinkController;
use App\Http\Controllers\Auth\PasswordForgottenController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\Files\StoredFileDownloadController;
use App\Http\Controllers\PlaceholderPageController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Sports\ClubController;
use App\Http\Controllers\Sports\CompetitionController;
use App\Http\Controllers\Sports\CompetitionSeasonController;
use App\Http\Controllers\Sports\SeasonController;
use App\Http\Controllers\Sports\SeasonSelectionController;
use App\Http\Controllers\Sports\StadiumController;
use App\Http\Controllers\Users\ProfileController;
use App\Http\Controllers\Users\UserAccessController;
use App\Http\Controllers\Users\UserController;
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

    // Access links sent by e-mail (M01 §5; D-277…D-280): activation, or setup again after the reset
    // of the second factor (password + second factor); new password after a reset by the admin.
    Route::get('attivazione/{token}', [AccessLinkController::class, 'showActivation'])->name('access.activation');
    Route::post('attivazione/{token}', [AccessLinkController::class, 'storeActivation'])
        ->middleware('throttle:10,1')
        ->name('access.activation.store');
    Route::get('nuova-password/{token}', [AccessLinkController::class, 'showReset'])->name('access.reset');
    Route::post('nuova-password/{token}', [AccessLinkController::class, 'storeReset'])
        ->middleware('throttle:10,1')
        ->name('access.reset.store');

    // Self-service reset of the password (D-279; PR-08): same answer for every address; at most
    // 5 requests in 15 minutes from the same IP address, 3 links per address in an hour (Action).
    Route::get('password-dimenticata', [PasswordForgottenController::class, 'create'])->name('password.request');
    Route::post('password-dimenticata', [PasswordForgottenController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.email');
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

        // Menu pages (PR-02, D-269), each behind the Gate of its area (M01 §4); the pages not built
        // yet show "Funzione in arrivo".
        foreach (PortalPages::PAGES as $key => [$path, $ability]) {
            $route = array_key_exists($key, PortalPages::CONTROLLERS)
                ? Route::get($path, PortalPages::CONTROLLERS[$key])
                : Route::get($path, PlaceholderPageController::class)->defaults('page', $key);

            $route->middleware('can:'.$ability->value)->name(PortalPages::routeName($key));
        }

        // Personal profile of every role (D-267): data and change of the password (D-273).
        Route::middleware('can:'.Ability::Profile->value)->group(function (): void {
            Route::put('profilo', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('profilo/password', [ProfileController::class, 'password'])
                ->middleware('throttle:10,1')
                ->name('profile.password');
        });

        // Stored files (D-218, C10): the only way to read a file of the private disk, each file
        // checked by StoredFilePolicy. Files are stored by the domain Actions, not by a route.
        Route::get('file/{storedFile}', StoredFileDownloadController::class)
            ->whereNumber('storedFile')
            ->middleware('can:view,storedFile')
            ->name('files.download');

        // Users (M01 §5; D-139): admin only. The list is the "utenti" page above.
        Route::middleware('can:'.Ability::UsersManage->value)->group(function (): void {
            Route::get('utenti/nuovo', [UserController::class, 'create'])->name('users.create');
            Route::post('utenti', [UserController::class, 'store'])->name('users.store');
            Route::get('utenti/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
            Route::put('utenti/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::post('utenti/{user}/disattiva', [UserAccessController::class, 'deactivate'])->whereNumber('user')->name('users.deactivate');
            Route::post('utenti/{user}/riattiva', [UserAccessController::class, 'reactivate'])->whereNumber('user')->name('users.reactivate');
            Route::post('utenti/{user}/reset-password', [UserAccessController::class, 'resetPassword'])->whereNumber('user')->name('users.reset-password');
            Route::post('utenti/{user}/reset-verifica', [UserAccessController::class, 'resetTwoFactor'])->whereNumber('user')->name('users.reset-two-factor');
            Route::post('utenti/{user}/invia-link', [UserAccessController::class, 'resendLink'])->whereNumber('user')->name('users.resend-link');
        });

        // Season of the top bar (D-317, F1-01): every role, kept in the session.
        Route::post('stagione', SeasonSelectionController::class)->name('season.select');

        // Sports registry (M02; D-137): seasons, competitions and their editions, admin only.
        // The lists are the "anagrafiche/stagioni" and "anagrafiche/competizioni" pages above.
        Route::middleware('can:'.Ability::SportsStructureManage->value)->group(function (): void {
            Route::get('anagrafiche/stagioni/nuova', [SeasonController::class, 'create'])->name('sports.seasons.create');
            Route::post('anagrafiche/stagioni', [SeasonController::class, 'store'])->name('sports.seasons.store');
            Route::get('anagrafiche/stagioni/{season}', [SeasonController::class, 'edit'])->whereNumber('season')->name('sports.seasons.edit');
            Route::put('anagrafiche/stagioni/{season}', [SeasonController::class, 'update'])->whereNumber('season')->name('sports.seasons.update');

            Route::post('anagrafiche/stagioni/{season}/competizioni', [CompetitionSeasonController::class, 'store'])
                ->whereNumber('season')
                ->name('sports.editions.store');
            Route::put('anagrafiche/edizioni/{competitionSeason}', [CompetitionSeasonController::class, 'update'])
                ->whereNumber('competitionSeason')
                ->name('sports.editions.update');
            Route::delete('anagrafiche/edizioni/{competitionSeason}', [CompetitionSeasonController::class, 'destroy'])
                ->whereNumber('competitionSeason')
                ->name('sports.editions.destroy');

            Route::get('anagrafiche/competizioni/nuova', [CompetitionController::class, 'create'])->name('sports.competitions.create');
            Route::post('anagrafiche/competizioni', [CompetitionController::class, 'store'])->name('sports.competitions.store');
            Route::get('anagrafiche/competizioni/{competition}', [CompetitionController::class, 'edit'])->whereNumber('competition')->name('sports.competitions.edit');
            Route::put('anagrafiche/competizioni/{competition}', [CompetitionController::class, 'update'])->whereNumber('competition')->name('sports.competitions.update');

            // Clubs with logo and aliases (F-05, F-11); never deleted (C4, RB-08).
            Route::get('anagrafiche/club/nuovo', [ClubController::class, 'create'])->name('sports.clubs.create');
            Route::post('anagrafiche/club', [ClubController::class, 'store'])->name('sports.clubs.store');
            Route::get('anagrafiche/club/{club}', [ClubController::class, 'edit'])->whereNumber('club')->name('sports.clubs.edit');
            Route::put('anagrafiche/club/{club}', [ClubController::class, 'update'])->whereNumber('club')->name('sports.clubs.update');
            Route::post('anagrafiche/club/{club}/logo', [ClubController::class, 'logo'])->whereNumber('club')->name('sports.clubs.logo');
            Route::post('anagrafiche/club/{club}/alias', [ClubController::class, 'storeAlias'])->whereNumber('club')->name('sports.clubs.aliases.store');
            Route::delete('anagrafiche/alias/{clubAlias}', [ClubController::class, 'destroyAlias'])->whereNumber('clubAlias')->name('sports.clubs.aliases.destroy');
        });

        // Stadiums (F-07): admin and manager (D-137). The list is the "anagrafiche/stadi" page above.
        Route::middleware('can:'.Ability::SportsCalendarsManage->value)->group(function (): void {
            Route::get('anagrafiche/stadi/nuovo', [StadiumController::class, 'create'])->name('sports.stadiums.create');
            Route::post('anagrafiche/stadi', [StadiumController::class, 'store'])->name('sports.stadiums.store');
            Route::get('anagrafiche/stadi/{stadium}', [StadiumController::class, 'edit'])->whereNumber('stadium')->name('sports.stadiums.edit');
            Route::put('anagrafiche/stadi/{stadium}', [StadiumController::class, 'update'])->whereNumber('stadium')->name('sports.stadiums.update');
        });

        // System settings (D-191, D-286; PR-07): admin only. The page is "impostazioni" above.
        Route::put('impostazioni', [SettingsController::class, 'update'])
            ->middleware('can:'.Ability::SettingsManage->value)
            ->name('settings.update');
    });
});
