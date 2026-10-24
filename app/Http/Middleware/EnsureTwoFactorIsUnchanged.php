<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Text;
use App\Support\Toast;
use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the sessions opened with a second factor that no longer exists (D-280).
 *
 * auth.session closes the other sessions when the password changes (D-273), but the reset of the
 * second factor from the server (users:reset-two-factor, D-276) keeps the password: without this
 * check a session left open on a lost smartphone would stay valid, and would even be sent to the
 * setup page to configure a new second factor in place of the user.
 *
 * The session keeps the moment the second factor of the user was confirmed (0 when it was not
 * configured), written at the login and at the confirmation of the setup. When it no longer
 * matches the user, the session is closed and the user goes back to the login.
 */
class EnsureTwoFactorIsUnchanged
{
    public const SESSION_KEY = 'auth.two_factor_stamp';

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

        if (! $session->exists(self::SESSION_KEY)) {
            self::remember($session, $user);

            return $next($request);
        }

        if ($session->get(self::SESSION_KEY) !== self::stamp($user)) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('toast', Toast::error(Text::get('ui.auth.two_factor_reset')));
        }

        return $next($request);
    }

    /**
     * Records in the session the second factor the user has now (login, confirmed setup).
     */
    public static function remember(Session $session, User $user): void
    {
        $session->put(self::SESSION_KEY, self::stamp($user));
    }

    /**
     * Moment the second factor was confirmed, 0 when the user has none.
     */
    public static function stamp(User $user): int
    {
        return $user->two_factor_confirmed_at?->getTimestamp() ?? 0;
    }
}
