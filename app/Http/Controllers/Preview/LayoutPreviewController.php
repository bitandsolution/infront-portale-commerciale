<?php

namespace App\Http\Controllers\Preview;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Support\Navigation\NavigationMenu;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Layout preview before users exist (PR-01, D-264). Local and testing environments only.
 *
 * Shows the application layout with the menu of a role, the placeholders of the top bar
 * and the shared states (empty, loading, error, toast). Removed by PR-03, with the development access.
 */
class LayoutPreviewController extends Controller
{
    /** URL segment → role. */
    public const ROLES = [
        'agente' => Role::Agent,
        'gestore' => Role::Manager,
        'admin' => Role::Admin,
    ];

    public function show(Request $request, string $role): Response
    {
        $currentRole = self::ROLES[$role];

        $active = $request->query('voce');
        if (! is_string($active) || ! in_array($active, NavigationMenu::pageKeys(), true)) {
            $active = 'dashboard';
        }

        $navigation = NavigationMenu::forRole(
            $currentRole,
            $active,
            fn (string $key): string => route('preview.layout', ['role' => $role, 'voce' => $key]),
        );

        return Inertia::render('Preview/Layout', [
            'layout' => [
                'title' => NavigationMenu::label($active),
                'navigation' => $navigation,
                'user' => [
                    'name' => __('ui.preview.user_name'),
                    'initials' => __('ui.preview.user_initials'),
                    'role' => $currentRole->label(),
                ],
                'season' => [
                    'label' => __('ui.preview.season'),
                    'enabled' => false,
                ],
            ],
            'roles' => collect(self::ROLES)->map(fn (Role $r, string $slug): array => [
                'slug' => $slug,
                'label' => $r->label(),
                'href' => route('preview.layout', ['role' => $slug]),
                'current' => $slug === $role,
            ])->values()->all(),
            'errorUrl' => route('preview.error'),
        ]);
    }

    /**
     * Simulates a system error: the exception handler logs it with a support code
     * and sends the user back with a toast (D-265).
     */
    public function error(): never
    {
        throw new RuntimeException('Simulated system error from the layout preview.');
    }
}
