<?php

namespace App\Support\Navigation;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Back to the page after an expired session (ux-architettura §4.5, D-375): when the session ends on
 * a form submission (e.g. the automatic save of a draft), the page to reopen after the login is the
 * page the request came from, read from the Referer header: a submission itself cannot be repeated.
 * Only a page of the portal is accepted, never another host.
 */
final class ReturnAfterLogin
{
    public static function toLogin(Request $request): RedirectResponse
    {
        $page = self::previousPage($request);

        if ($page !== null) {
            redirect()->setIntendedUrl($page);
        }

        // 303: the browser follows it with a GET also after a PUT or a DELETE.
        return redirect()->route('login', [], 303);
    }

    public static function previousPage(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $root = rtrim($request->getSchemeAndHttpHost(), '/').'/';

        return str_starts_with($referer, $root) && ! str_starts_with($referer, route('login')) ? $referer : null;
    }
}
