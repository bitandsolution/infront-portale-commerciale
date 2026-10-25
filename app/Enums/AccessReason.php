<?php

namespace App\Enums;

/**
 * Reason of a failed login, of a session closed by the portal or of a self-service reset that sent
 * no link (PR-08), stored in audit_events.reason for
 * the access events (D-128, D-283). Stable codes, translated when shown (lang/it/audit.php).
 */
enum AccessReason: string
{
    /** The e-mail matches no user (the login page answers as for a wrong password, D-214). */
    case UnknownEmail = 'unknown_email';

    /** Wrong password for an existing user. */
    case InvalidCredentials = 'invalid_credentials';

    /** User created but not activated yet (D-278). */
    case NotActivated = 'not_activated';

    /** Deactivated user (RB-05). */
    case Deactivated = 'deactivated';

    /** Attempt made while the e-mail and IP pair is locked (D-274). */
    case Locked = 'locked';

    /** Self-service reset asked by an active user who has no second factor yet (PR-08): no link. */
    case TwoFactorNotConfigured = 'two_factor_not_configured';

    /** Self-service reset asked more than 3 times in an hour for the same address (PR-08): no link. */
    case Throttled = 'throttled';

    /** Session expired after the inactivity limit of the role (D-214). */
    case Idle = 'idle';

    /** Session opened with a second factor reset in the meantime (D-280). */
    case TwoFactorReset = 'two_factor_reset';

    public function label(): string
    {
        $label = __('audit.reasons.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
