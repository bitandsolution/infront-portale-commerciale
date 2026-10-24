<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Fortify (D-214, progettazione-tecnica §5.4.2)
|--------------------------------------------------------------------------
|
| Fortify is used for its authentication logic only: its routes are not
| registered (App\Providers\FortifyServiceProvider::register) and the portal
| declares its own in routes/web.php, so every route is in the route × role
| matrix (D-215). Pages are Inertia pages of the portal (resources/js/pages/Auth).
|
| Only two-factor authentication is enabled. Registration, password reset,
| profile and passkeys are not part of the portal (users are created by the
| admin from PR-04; "remember device" and passkeys are deferred, EV-23).
|
*/

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // Where a successful login goes when no page was requested before it.
    'home' => '/dashboard',

    'redirects' => [
        'logout' => '/accesso',
    ],

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    // The lockout (5 attempts, 15 minutes, e-mail + IP) is App\Domain\Users\Auth\LoginRateLimiter,
    // used inside the login pipeline: no extra route throttle.
    'limiters' => [
        'login' => null,
        'two-factor' => null,
        'passkeys' => null,
    ],

    'views' => false,

    'features' => [
        // D-136: two-factor for every account; "confirm" = active only after a valid code.
        // Password confirmation is not asked: setup happens right after the password login.
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]),
    ],

];
