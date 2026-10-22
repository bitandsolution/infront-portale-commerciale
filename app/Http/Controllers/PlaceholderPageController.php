<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DevLogin;
use App\Support\Navigation\LayoutProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages of the menu not built yet (PR-02, D-269): the real layout of the signed-in user
 * with the "coming soon" empty state. Authorization is on the route (auth + area Gate).
 */
class PlaceholderPageController extends Controller
{
    public function __invoke(Request $request, string $page): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Placeholder', [
            'layout' => LayoutProps::for($user, $page),
            'devLoginUrl' => DevLogin::enabled() ? route('dev.login') : null,
        ]);
    }
}
