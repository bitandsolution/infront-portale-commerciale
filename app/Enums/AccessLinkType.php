<?php

namespace App\Enums;

/**
 * Kinds of access link sent to a user (M01 §5, D-209). Every link is valid for 48 hours and once.
 *
 * - activation: new user, sets password and second factor;
 * - two_factor_reset: the admin reset the second factor, the user sets password and second
 *   factor again on the same page as the activation (D-280);
 * - password_reset: the admin reset the password, the user sets a new one and confirms it with
 *   the code of his authenticator app (D-279);
 * - password_forgotten: the user asked for it from the login page (PR-08); same page as
 *   password_reset, but the current password keeps working until the new one is set.
 */
enum AccessLinkType: string
{
    case Activation = 'activation';
    case TwoFactorReset = 'two_factor_reset';
    case PasswordReset = 'password_reset';
    case PasswordForgotten = 'password_forgotten';

    /**
     * Page of the link: password and second factor, or new password only.
     */
    public function routeName(): string
    {
        return match ($this) {
            self::Activation, self::TwoFactorReset => 'access.activation',
            self::PasswordReset, self::PasswordForgotten => 'access.reset',
        };
    }
}
