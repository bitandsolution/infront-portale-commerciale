<?php

namespace App\Http\Middleware;

use App\Support\DevLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second guard of the development access (D-268): 404 whenever it is not enabled,
 * even if the routes were registered by mistake.
 */
class EnsureDevLoginEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(DevLogin::enabled(), 404);

        return $next($request);
    }
}
