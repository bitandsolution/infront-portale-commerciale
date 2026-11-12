<?php

use App\Enums\Ability;
use App\Http\Controllers\Auth\AccessLinkController;
use App\Http\Controllers\Auth\PasswordForgottenController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\Catalog\BenefitController;
use App\Http\Controllers\Catalog\CatalogValueController;
use App\Http\Controllers\Catalog\OnTopAssetController;
use App\Http\Controllers\Catalog\PriceListController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientManagementController;
use App\Http\Controllers\Clients\CommoditySectorController;
use App\Http\Controllers\Content\SlideController;
use App\Http\Controllers\Files\StoredFileDownloadController;
use App\Http\Controllers\Inventory\CapacityController;
use App\Http\Controllers\PlaceholderPageController;
use App\Http\Controllers\Proposals\ProposalClientController;
use App\Http\Controllers\Proposals\ProposalController;
use App\Http\Controllers\Proposals\ProposalCopyController;
use App\Http\Controllers\Proposals\ProposalGroupController;
use App\Http\Controllers\Proposals\ProposalVariantController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Sports\CalendarImportController;
use App\Http\Controllers\Sports\ClubController;
use App\Http\Controllers\Sports\CompetitionController;
use App\Http\Controllers\Sports\CompetitionSeasonController;
use App\Http\Controllers\Sports\EditionController;
use App\Http\Controllers\Sports\MatchController;
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

            // Clubs enrolled in an edition (F-06), from the page of the season.
            Route::get('anagrafiche/edizioni/{competitionSeason}', [EditionController::class, 'show'])
                ->whereNumber('competitionSeason')
                ->name('sports.editions.show');
            Route::post('anagrafiche/edizioni/{competitionSeason}/iscrizioni', [EditionController::class, 'enroll'])
                ->whereNumber('competitionSeason')
                ->name('sports.enrollments.store');
            Route::delete('anagrafiche/iscrizioni/{clubEnrollment}', [EditionController::class, 'destroy'])
                ->whereNumber('clubEnrollment')
                ->name('sports.enrollments.destroy');

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

            // Matches (F-08): manual entry and change, cancellation and restore (D-319), never deleted.
            // Calendar import (F-09, FL-02; D-325): upload, preview, corrections, confirmation.
            Route::post('anagrafiche/import', [CalendarImportController::class, 'store'])->name('sports.imports.store');
            Route::get('anagrafiche/import/{calendarImport}', [CalendarImportController::class, 'show'])->whereNumber('calendarImport')->name('sports.imports.show');
            Route::put('anagrafiche/import/{calendarImport}/colonne', [CalendarImportController::class, 'columns'])->whereNumber('calendarImport')->name('sports.imports.columns');
            Route::post('anagrafiche/import/{calendarImport}/associa', [CalendarImportController::class, 'resolve'])->whereNumber('calendarImport')->name('sports.imports.resolve');
            Route::post('anagrafiche/import/{calendarImport}/ricalcola', [CalendarImportController::class, 'refresh'])->whereNumber('calendarImport')->name('sports.imports.refresh');
            Route::post('anagrafiche/import/{calendarImport}/conferma', [CalendarImportController::class, 'confirm'])->whereNumber('calendarImport')->name('sports.imports.confirm');
            Route::post('anagrafiche/import/{calendarImport}/scarta', [CalendarImportController::class, 'discard'])->whereNumber('calendarImport')->name('sports.imports.discard');

            Route::get('anagrafiche/partite/nuova', [MatchController::class, 'create'])->name('sports.matches.create');
            Route::post('anagrafiche/partite', [MatchController::class, 'store'])->name('sports.matches.store');
            Route::get('anagrafiche/partite/{fixtureMatch}', [MatchController::class, 'edit'])->whereNumber('fixtureMatch')->name('sports.matches.edit');
            Route::put('anagrafiche/partite/{fixtureMatch}', [MatchController::class, 'update'])->whereNumber('fixtureMatch')->name('sports.matches.update');
            Route::post('anagrafiche/partite/{fixtureMatch}/annulla', [MatchController::class, 'cancel'])->whereNumber('fixtureMatch')->name('sports.matches.cancel');
            Route::post('anagrafiche/partite/{fixtureMatch}/ripristina', [MatchController::class, 'restore'])->whereNumber('fixtureMatch')->name('sports.matches.restore');
        });

        // Commodity sectors (M05 F-05; D-357): admin only. The list is the "clienti/settori" page
        // above; sectors are deactivated, never deleted (RB-08).
        Route::middleware('can:'.Ability::CommoditySectorsManage->value)->group(function (): void {
            Route::get('clienti/settori/nuovo', [CommoditySectorController::class, 'create'])->name('clients.sectors.create');
            Route::post('clienti/settori', [CommoditySectorController::class, 'store'])->name('clients.sectors.store');
            Route::get('clienti/settori/{commoditySector}', [CommoditySectorController::class, 'edit'])->whereNumber('commoditySector')->name('clients.sectors.edit');
            Route::put('clienti/settori/{commoditySector}', [CommoditySectorController::class, 'update'])->whereNumber('commoditySector')->name('clients.sectors.update');
        });

        // Slide library (M08 F-01…F-03; D-109, D-406): admin and manager. The list is the
        // "contenuti/slide" page above; slides are deactivated, never deleted (C4). The change is a
        // multipart request with the image (PUT spoofed as POST).
        Route::middleware('can:'.Ability::ContentManage->value)->group(function (): void {
            Route::get('contenuti/slide/nuova', [SlideController::class, 'create'])->name('content.slides.create');
            Route::post('contenuti/slide', [SlideController::class, 'store'])->name('content.slides.store');
            Route::get('contenuti/slide/{slide}', [SlideController::class, 'edit'])->whereNumber('slide')->name('content.slides.edit');
            Route::put('contenuti/slide/{slide}', [SlideController::class, 'update'])->whereNumber('slide')->name('content.slides.update');
        });

        // Clients (M05 F-01, F-03, F-04; D-076, D-078, D-358). New client: agent and admin. Page and
        // change of a client: ClientPolicy (the agent only his own, the manager read-only). Never
        // deleted (C4, RB-06). The lists are the "clienti/miei" and "clienti" pages above.
        Route::middleware('can:'.Ability::ClientsOwn->value)->group(function (): void {
            Route::get('clienti/nuovo', [ClientController::class, 'create'])->name('clients.create');
            Route::post('clienti', [ClientController::class, 'store'])->name('clients.store');
        });
        Route::get('clienti/{client}', [ClientController::class, 'edit'])->whereNumber('client')->name('clients.edit');
        Route::put('clienti/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');

        // Reassignment, deactivation, reactivation and merge of a client (M05 F-07, F-08, F-09;
        // D-082, D-359): admin only, from the page of the client.
        Route::middleware('can:'.Ability::ClientsManageAll->value)->group(function (): void {
            Route::post('clienti/{client}/riassegna', [ClientManagementController::class, 'reassign'])->whereNumber('client')->name('clients.reassign');
            Route::post('clienti/{client}/disattiva', [ClientManagementController::class, 'deactivate'])->whereNumber('client')->name('clients.deactivate');
            Route::post('clienti/{client}/riattiva', [ClientManagementController::class, 'reactivate'])->whereNumber('client')->name('clients.reactivate');
            Route::post('clienti/{client}/unisci', [ClientManagementController::class, 'merge'])->whereNumber('client')->name('clients.merge');
        });

        // Drafts of the configurator (M06 §4; D-372…D-375; F4-02): the agent, on his own drafts
        // (ProposalPolicy). The pages are "proposte/nuova" and "proposte/mie" above. The draft is born
        // with the client (store, or a new client created at step 1), is saved automatically with its
        // lock version (update, at most 60 saves a minute) and is deleted only while it is a draft.
        Route::middleware('can:'.Ability::ProposalsOwn->value)->group(function (): void {
            Route::post('proposte', [ProposalController::class, 'store'])->name('proposals.store');
            Route::post('proposte/nuova/cliente', [ProposalClientController::class, 'store'])->name('proposals.clients.store');
            Route::get('proposte/{proposal}', [ProposalController::class, 'edit'])->whereNumber('proposal')->name('proposals.edit');
            Route::put('proposte/{proposal}', [ProposalController::class, 'update'])
                ->whereNumber('proposal')
                ->middleware('throttle:60,1')
                ->name('proposals.update');
            Route::post('proposte/{proposal}/cliente', [ProposalClientController::class, 'update'])->whereNumber('proposal')->name('proposals.clients.update');
            Route::delete('proposte/{proposal}', [ProposalController::class, 'destroy'])->whereNumber('proposal')->name('proposals.destroy');
            // Support functions (M06 §5; F4-05): "Copia su altri club" on a draft, "Duplica proposta"
            // from any own proposal (ProposalPolicy::duplicate).
            Route::post('proposte/{proposal}/copia-club', [ProposalCopyController::class, 'clubs'])->whereNumber('proposal')->name('proposals.copy-clubs');
            Route::post('proposte/{proposal}/duplica', [ProposalCopyController::class, 'duplicate'])->whereNumber('proposal')->name('proposals.duplicate');
            // Variants of a group of proposals (D-396; DM-01): a copy of the draft in its group.
            Route::post('proposte/{proposal}/varianti', [ProposalVariantController::class, 'store'])->whereNumber('proposal')->name('proposals.variants.store');
            // Sending of a group of variants for approval (D-395, D-412; DM-03a).
            Route::post('proposte/gruppi/{group}/invio', [ProposalGroupController::class, 'submit'])->whereNumber('group')->name('proposals.groups.submit');
        });

        // A group of variants (D-411; DM-03a): its detail for admin, manager and its agent
        // (ProposalGroupPolicy::view); the rejection for admin and manager. The queue is the
        // "proposte/da-approvare" page above.
        Route::get('proposte/gruppi/{group}', [ProposalGroupController::class, 'show'])->whereNumber('group')->name('proposals.groups.show');
        Route::post('proposte/gruppi/{group}/rifiuto', [ProposalGroupController::class, 'reject'])
            ->middleware('can:'.Ability::ProposalsReview->value)
            ->whereNumber('group')
            ->name('proposals.groups.reject');

        // Benefit and on-top texts of a club (M03 F-08, F-10; D-335): admin and manager read them.
        Route::get('catalogo/listini/{priceList}/club/{club}/benefit', [BenefitController::class, 'show'])
            ->middleware('can:'.Ability::CatalogView->value)
            ->whereNumber(['priceList', 'club'])
            ->name('catalog.benefits.show');

        // Catalogue values (M03 F-01…F-04, F-06; D-331): admin only. The list is the
        // "catalogo/valori" page above; codes come from the system data, nothing is created or deleted.
        Route::middleware('can:'.Ability::CatalogManage->value)->group(function (): void {
            Route::get('catalogo/valori/format/{ledFormat}', [CatalogValueController::class, 'editFormat'])->whereNumber('ledFormat')->name('catalog.values.formats.edit');
            Route::put('catalogo/valori/format/{ledFormat}', [CatalogValueController::class, 'updateFormat'])->whereNumber('ledFormat')->name('catalog.values.formats.update');
            Route::get('catalogo/valori/livelli/{partnershipTier}', [CatalogValueController::class, 'editTier'])->whereNumber('partnershipTier')->name('catalog.values.tiers.edit');
            Route::put('catalogo/valori/livelli/{partnershipTier}', [CatalogValueController::class, 'updateTier'])->whereNumber('partnershipTier')->name('catalog.values.tiers.update');
            Route::get('catalogo/valori/varianti/{hospitalityVariant}', [CatalogValueController::class, 'editVariant'])->whereNumber('hospitalityVariant')->name('catalog.values.variants.edit');
            Route::put('catalogo/valori/varianti/{hospitalityVariant}', [CatalogValueController::class, 'updateVariant'])->whereNumber('hospitalityVariant')->name('catalog.values.variants.update');
            Route::get('catalogo/valori/categorie/{benefitCategory}', [CatalogValueController::class, 'editCategory'])->whereNumber('benefitCategory')->name('catalog.values.categories.edit');
            Route::put('catalogo/valori/categorie/{benefitCategory}', [CatalogValueController::class, 'updateCategory'])->whereNumber('benefitCategory')->name('catalog.values.categories.update');

            // Price list of a season, prices, benefits and on-top texts (M03 F-05, F-07…F-11; D-333,
            // D-335): the grids are the "catalogo/led|partnership|hospitality" pages above
            // (catalog.view, the manager reads them); writes admin only.
            Route::post('catalogo/listini', [PriceListController::class, 'store'])->name('catalog.price-lists.store');
            Route::put('catalogo/listini/{priceList}/prezzi/{kind}', [PriceListController::class, 'updatePrice'])->whereNumber('priceList')->whereIn('kind', ['led', 'partnership', 'hospitality'])->name('catalog.prices.update');
            Route::post('catalogo/listini/{priceList}/pubblica', [PriceListController::class, 'publish'])->whereNumber('priceList')->name('catalog.price-lists.publish');
            Route::put('catalogo/listini/{priceList}/club/{club}/benefit', [BenefitController::class, 'update'])->whereNumber(['priceList', 'club'])->name('catalog.benefits.update');
            Route::post('catalogo/listini/{priceList}/on-top', [OnTopAssetController::class, 'store'])->whereNumber('priceList')->name('catalog.on-top.store');
            Route::put('catalogo/on-top/{onTopAsset}', [OnTopAssetController::class, 'update'])->whereNumber('onTopAsset')->name('catalog.on-top.update');
            Route::delete('catalogo/on-top/{onTopAsset}', [OnTopAssetController::class, 'destroy'])->whereNumber('onTopAsset')->name('catalog.on-top.destroy');
        });

        // Capacities of the inventory (M04 F-01…F-03; D-191, D-337): admin only. The list is the
        // "magazzino/capacita" page above.
        Route::middleware('can:'.Ability::InventoryCapacityManage->value)->group(function (): void {
            Route::get('magazzino/capacita/club/{club}', [CapacityController::class, 'edit'])->whereNumber('club')->name('inventory.capacities.edit');
            Route::put('magazzino/capacita/club/{club}', [CapacityController::class, 'update'])->whereNumber('club')->name('inventory.capacities.update');
        });

        // System settings (D-191, D-286; PR-07): admin only. The page is "impostazioni" above.
        Route::put('impostazioni', [SettingsController::class, 'update'])
            ->middleware('can:'.Ability::SettingsManage->value)
            ->name('settings.update');
    });
});
