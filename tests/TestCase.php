<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Acting as another user starts a new session, as after a real login.
     *
     * The session keeps the stamp of the second factor (EnsureTwoFactorIsUnchanged, D-280) and the
     * password hash (auth.session, D-273) of the user who opened it. Reusing it for a different user
     * closes it with a redirect to the login whenever the two users differ, for example when the
     * factory confirmed their second factor in two different seconds: a slow machine made such
     * tests fail at random.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $current = $this->app['auth']->guard($guard);

        if ($current->hasUser() && $current->user()?->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }
}
