<?php

namespace App\Domain\Users\Auth;

use Closure;
use Illuminate\Http\Request;

/**
 * First step of the login pipeline: the portal has no "remember me" (D-214; "remember device"
 * deferred, EV-23). A remember cookie would sign the user in again after the session expired,
 * skipping the second factor and the idle limit of his role, so the field is ignored even when
 * a request sends it.
 */
class DisableRememberMe
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $request->merge(['remember' => false]);

        return $next($request);
    }
}
