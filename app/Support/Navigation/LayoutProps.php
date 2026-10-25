<?php

namespace App\Support\Navigation;

use App\Domain\Sports\Services\CurrentSeason;
use App\Models\Season;
use App\Models\User;

/**
 * Props of the application layout (resources/js/layouts/AppLayout.vue) for the signed-in user:
 * the menu of his role (D-161, filtered on the server), his name and role, his profile (D-267),
 * the logout and the season selector (D-317).
 */
final class LayoutProps
{
    /**
     * @return array{title: string, navigation: list<array<string, mixed>>, user: array{name: string, initials: string, role: string, profileUrl: string, logoutUrl: string}, season: array{label: string, enabled: bool, current: int|null, options: list<array{value: string, label: string}>, selectUrl: string}}
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
            'season' => self::season(),
        ];
    }

    /**
     * Season selector (D-317): every season, the most recent first; disabled when none exists.
     *
     * @return array{label: string, enabled: bool, current: int|null, options: list<array{value: string, label: string}>, selectUrl: string}
     */
    private static function season(): array
    {
        $current = app(CurrentSeason::class)->get();
        $options = array_values(Season::query()
            ->orderByDesc('starts_on')
            ->get(['id', 'code'])
            ->map(fn (Season $season): array => ['value' => (string) $season->id, 'label' => $season->code])
            ->all());

        return [
            'label' => $current?->code ?? self::text('ui.season.none'),
            'enabled' => $current !== null,
            'current' => $current?->id,
            'options' => $options,
            'selectUrl' => route('season.select'),
        ];
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
