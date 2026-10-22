<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The layout preview exists only in the local and testing environments (D-264).
 *
 * The routes are registered only there; this middleware is a second guard,
 * so a misconfigured environment answers 404 anyway.
 */
class EnsureLayoutPreviewEnabled
{
    /** @var list<string> */
    public const ENVIRONMENTS = ['local', 'testing'];

    public static function enabled(): bool
    {
        return in_array(app()->environment(), self::ENVIRONMENTS, true);
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::enabled(), 404);

        return $next($request);
    }
}
