<?php

namespace App\Support;

/**
 * Development access (D-268): sign in as any user without a password, to check roles and
 * permissions on the local machine before the real login (PR-03), which removes it.
 *
 * Three guards keep it out of staging and production:
 * 1. the routes are registered only when this returns true (routes/web.php);
 * 2. EnsureDevLoginEnabled answers 404 if the condition is no longer true at request time;
 * 3. tests check that production and staging never expose it, even with DEV_LOGIN=true.
 *
 * "testing" is allowed only so that the automated tests can cover it.
 */
final class DevLogin
{
    /** @var list<string> */
    public const ENVIRONMENTS = ['local', 'testing'];

    public static function enabled(): bool
    {
        return in_array(app()->environment(), self::ENVIRONMENTS, true)
            && config('app.dev_login') === true;
    }
}
