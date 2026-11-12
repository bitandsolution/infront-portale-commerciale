<?php

namespace Tests;

use App\Domain\Documents\Contracts\RendersPdf;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakePdfRenderer;

abstract class TestCase extends BaseTestCase
{
    /**
     * The documents of the approved groups (DM-04) render with a fake renderer: the queue is
     * synchronous in the tests, so every approval generates its document right away, without
     * Chromium. The tests of the group "pdf" use PdfPipeline itself.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(RendersPdf::class, FakePdfRenderer::class);
    }

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
