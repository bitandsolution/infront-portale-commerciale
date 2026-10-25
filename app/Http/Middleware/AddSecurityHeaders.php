<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Laravel\Horizon\Horizon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers of every response (progettazione-tecnica §5.2.4; D-176; PR-09).
 *
 * - X-Frame-Options DENY and frame-ancestors 'none': the portal is never shown inside a frame.
 * - X-Content-Type-Options nosniff, Referrer-Policy same-origin, a restrictive Permissions-Policy and
 *   Cross-Origin-Opener-Policy same-origin.
 * - HSTS on HTTPS only, one year, without includeSubDomains and preload until the domain is decided
 *   (D-180): both would bind every subdomain of a domain we do not know yet.
 * - Content-Security-Policy on HTML pages, enforced (not report-only), with a nonce per request:
 *   only the portal's own scripts, styles, fonts and images. The nonce goes to the tags of Vite, to
 *   the inline styles of the error pages and, through the "csp-nonce" meta tag, to the styles that
 *   Inertia adds (progress bar).
 * - The Horizon panel (/horizon, admin only, D-298) has a wider policy: its interface compiles its
 *   templates in the browser ('unsafe-eval'), writes inline styles and loads its font from
 *   fonts.bunny.net.
 *
 * Laravel's detailed error page (APP_DEBUG on, never in production) gets no CSP.
 * Files and JSON answers get the headers without the CSP (a download is not a page); an answer that
 * already has its own policy keeps it.
 */
class AddSecurityHeaders
{
    public const HSTS = 'max-age=31536000';

    public const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';

    private const HORIZON_FONTS = 'https://fonts.bunny.net';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        Horizon::$nonceAttribute = ' nonce="'.$nonce.'"';

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', self::HSTS);
        }

        // A response with its own policy keeps it (e.g. the downloads: "default-src 'none'; sandbox").
        if (str_starts_with((string) $headers->get('Content-Type'), 'text/html')
            && ! $headers->has('Content-Security-Policy')
            && ! self::isDebugErrorPage($response)) {
            $headers->set('Content-Security-Policy', $this->policy($request, $nonce));
        }

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $horizon = self::isHorizon($request);
        $hot = Vite::isRunningHot() ? self::viteDevServer() : null;

        $script = ["'self'", "'nonce-{$nonce}'"];
        $style = ["'self'", "'nonce-{$nonce}'"];
        $font = ["'self'"];
        $connect = ["'self'"];

        if ($horizon) {
            $script[] = "'unsafe-eval'";
            // With a nonce in the list browsers ignore 'unsafe-inline': the panel needs the latter.
            $style = ["'self'", "'unsafe-inline'", self::HORIZON_FONTS];
            $font[] = self::HORIZON_FONTS;
        }

        if ($hot !== null) {
            // "npm run dev" (development only): scripts and styles from the Vite server, styles added
            // as inline tags, live reload through a web socket.
            $script[] = $hot;
            $style = ["'self'", "'unsafe-inline'", $hot];
            $font[] = $hot;
            $connect[] = $hot;
            $connect[] = (string) preg_replace('~^http~', 'ws', $hot);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            'font-src '.implode(' ', $font),
            "img-src 'self' data: blob:",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * Laravel's error page with the details (APP_DEBUG on, development only) has inline scripts and
     * styles without nonce: it is left without a policy so it stays readable on the VM.
     */
    private static function isDebugErrorPage(Response $response): bool
    {
        return $response->getStatusCode() >= 500 && config('app.debug') === true;
    }

    private static function isHorizon(Request $request): bool
    {
        $path = config('horizon.path');
        $path = trim(is_string($path) && $path !== '' ? $path : 'horizon', '/');

        return $request->is($path, $path.'/*');
    }

    /**
     * Origin of the Vite development server, from the "hot" file it writes in public/.
     */
    private static function viteDevServer(): ?string
    {
        $url = trim((string) @file_get_contents(public_path('hot')));
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
