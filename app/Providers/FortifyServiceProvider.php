<?php

namespace App\Providers;

use App\Domain\Users\Auth\AuthenticateUser;
use App\Domain\Users\Auth\DisableRememberMe;
use App\Domain\Users\Auth\LoginRateLimiter;
use App\Http\Responses\Auth\FailedTwoFactorLoginResponse;
use App\Http\Responses\Auth\LockoutResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\LockoutResponse as LockoutResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * Login with Fortify and the portal's own pages (D-214, progettazione-tecnica §5.4.2).
 *
 * Fortify's routes are not registered: routes/web.php declares only the ones the portal uses,
 * so every route is in the route × role matrix (D-215) and no route of Fortify can switch the
 * second factor off (D-136).
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        // 5 attempts, 15 minutes, e-mail + IP; wrong passwords and wrong codes alike.
        $this->app->singleton(FortifyLoginRateLimiter::class, LoginRateLimiter::class);
        $this->app->singleton(LoginRateLimiter::class);
        $this->app->singleton(LockoutResponseContract::class, LockoutResponse::class);
        $this->app->singleton(FailedTwoFactorLoginResponseContract::class, FailedTwoFactorLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(fn (Request $request): mixed => $this->app->make(AuthenticateUser::class)($request));

        Fortify::authenticateThrough(fn (): array => [
            DisableRememberMe::class,
            EnsureLoginIsNotThrottled::class,
            CanonicalizeUsername::class,
            RedirectIfTwoFactorAuthenticatable::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]);

        Fortify::loginView(fn (Request $request): Response => Inertia::render('Auth/Login', [
            'loginUrl' => route('login.store'),
            'forgotUrl' => route('password.request'),
            'lockedSeconds' => $this->lockedSeconds($request),
        ]));

        Fortify::twoFactorChallengeView(fn (): Response => Inertia::render('Auth/TwoFactorChallenge', [
            'challengeUrl' => route('two-factor.login.store'),
            'loginUrl' => route('login'),
        ]));
    }

    /**
     * Seconds left of a lockout just answered by LockoutResponse (0 when there is none).
     */
    private function lockedSeconds(Request $request): int
    {
        $until = $request->session()->get(LockoutResponse::SESSION_KEY);

        return is_int($until) ? max(0, $until - now()->getTimestamp()) : 0;
    }
}
