<?php

namespace App\Support\Navigation;

use App\Models\User;

/**
 * Props of the application layout (resources/js/layouts/AppLayout.vue) for the signed-in user:
 * the menu of his role (D-161, filtered on the server), his name and role, his profile (D-267)
 * and the logout.
 */
final class LayoutProps
{
    /**
     * @return array{title: string, navigation: list<array<string, mixed>>, user: array{name: string, initials: string, role: string, profileUrl: string, logoutUrl: string}, season: array{label: string, enabled: bool}}
     */
    public static function for(User $user, string $activeKey): array
    {
        return [
            'title' => NavigationMenu::label($activeKey),
            'navigation' => NavigationMenu::forRole($user->role, $activeKey, PortalPages::url(...)),
            'user' => [
                'name' => $user->fullName(),
                'initials' => $user->initials(),
                'role' => $user->role->label(),
                'profileUrl' => PortalPages::url('profile'),
                'logoutUrl' => route('logout'),
            ],
            // Placeholder until the seasons arrive with slice F1.
            'season' => [
                'label' => self::text('ui.season.placeholder'),
                'enabled' => false,
            ],
        ];
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
