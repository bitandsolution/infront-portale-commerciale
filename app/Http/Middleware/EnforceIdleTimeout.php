<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session expiry by role after inactivity (M01 §6, D-214): 8 hours for the agent, 2 hours for
 * admin and manager. Laravel has a single session lifetime (SESSION_LIFETIME, set to the
 * longest limit, 480 minutes), so the shorter limits are applied here.
 *
 * When the limit is passed the session is closed and the user goes back to the login with a
 * message; after the login (with the second factor) he returns to the page he asked for
 * (ux-architettura §4.5, "Sessione scaduta").
 */
class EnforceIdleTimeout
{
    public const SESSION_KEY = 'auth.last_activity_at';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $lastActivity = $session->get(self::SESSION_KEY);
        $now = now()->getTimestamp();

        if (is_int($lastActivity) && $now - $lastActivity > $user->role->idleTimeoutMinutes() * 60) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            $toast = [
                'id' => (string) Str::uuid(),
                'type' => 'info',
                'message' => __('ui.auth.session_expired'),
            ];

            // Only a page can be reopened after the login, not a form submission.
            $response = $request->isMethod('GET') ? redirect()->guest(route('login')) : redirect()->route('login');

            return $response->with('toast', $toast);
        }

        $session->put(self::SESSION_KEY, $now);

        return $next($request);
    }
}
