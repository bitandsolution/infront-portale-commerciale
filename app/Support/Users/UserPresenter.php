<?php

namespace App\Support\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Users as the pages show them (M01 §5): status, second factor and last login, with the Italian
 * labels from lang/it/ui.php. Dates of the system are stored in UTC and shown in Europe/Rome (C7).
 */
final class UserPresenter
{
    public const TIMEZONE = 'Europe/Rome';

    /**
     * Status of the account: active, waiting for the activation link (never activated), deactivated.
     */
    public static function status(User $user): string
    {
        return match (true) {
            ! $user->is_active => 'inactive',
            $user->activated_at === null => 'pending',
            default => 'active',
        };
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, roleLabel: string, status: string, twoFactor: bool, lastLogin: string|null, url: string}
     */
    public static function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->last_name.' '.$user->first_name,
            'email' => $user->email,
            'role' => $user->role->value,
            'roleLabel' => $user->role->label(),
            'status' => self::status($user),
            'twoFactor' => $user->two_factor_confirmed_at !== null,
            'lastLogin' => self::dateTime($user->last_login_at),
            'url' => route('users.show', $user),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function roleOptions(): array
    {
        return array_map(
            fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
            Role::cases(),
        );
    }

    public static function dateTime(?Carbon $moment): ?string
    {
        return $moment?->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i');
    }
}
