<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor authentication is mandatory for every account (D-136, M01-CA1): until the user
 * has confirmed it, every portal page leads to the setup page. Only the setup pages and the
 * logout stay reachable (routes/web.php).
 */
class EnsureTwoFactorIsConfigured
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->two_factor_confirmed_at === null) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
