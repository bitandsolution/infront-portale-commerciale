<?php

namespace App\Domain\Users\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A user-management rule refuses the operation (M01 §8–§10). Nothing has been written.
 * In the portal the admin goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class UserActionNotAllowed extends RuntimeException
{
    /** RB-04, M01-CA5: the last active admin can be neither deactivated nor moved to another role. */
    public static function lastActiveAdmin(): self
    {
        return new self(Text::get('users.errors.last_active_admin'));
    }

    /** The admin does not deactivate himself, change his own role or reset his own access (D-281). */
    public static function ownAccount(): self
    {
        return new self(Text::get('users.errors.own_account'));
    }

    public static function inactive(): self
    {
        return new self(Text::get('users.errors.inactive'));
    }

    /** Password reset only for a user with the second factor: he confirms it with the app code (D-279). */
    public static function twoFactorNotConfigured(): self
    {
        return new self(Text::get('users.errors.two_factor_not_configured'));
    }

    /** New access link only for a user who has still to set his password and second factor. */
    public static function alreadyConfigured(): self
    {
        return new self(Text::get('users.errors.already_configured'));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
