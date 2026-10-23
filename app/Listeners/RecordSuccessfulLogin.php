<?php

namespace App\Listeners;

use App\Http\Middleware\EnforceIdleTimeout;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * After every completed login (password, and second factor when configured):
 * - users.last_login_at (D-209);
 * - start of the inactivity count of the new session (EnforceIdleTimeout, D-214).
 *
 * The audit event of the login arrives with PR-05.
 */
class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->save();

        if (app()->bound('session.store')) {
            session()->put(EnforceIdleTimeout::SESSION_KEY, now()->getTimestamp());
        }
    }
}
