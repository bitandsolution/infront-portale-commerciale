<?php

namespace App\Enums;

/**
 * Action of an audit event (M10 §4.1; D-127, D-128), stored in audit_events.action (C8).
 *
 * The values are stable names: they are what the audit page and report R-07 (slice F8) filter on,
 * so an existing value is never renamed. The area is part of the action.
 */
enum AuditAction: string
{
    // Access (D-128, D-274): with the IP address (M10 §4.2).
    case LoginSucceeded = 'access.login_succeeded';
    case LoginFailed = 'access.login_failed';
    case Logout = 'access.logout';
    case LockedOut = 'access.locked_out';
    case TwoFactorFailed = 'access.two_factor_failed';
    case RecoveryCodeUsed = 'access.recovery_code_used';
    case SessionExpired = 'access.session_expired';
    case SessionClosed = 'access.session_closed';

    // Users (M01 §5): the names announced in the comments of App\Domain\Users\Actions.
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserRoleChanged = 'user.role_changed';
    case UserDeactivated = 'user.deactivated';
    case UserReactivated = 'user.reactivated';
    case UserAccessLinkSent = 'user.access_link_sent';
    case UserPasswordResetRequested = 'user.password_reset_requested';
    case UserPasswordReset = 'user.password_reset';
    case UserPasswordChanged = 'user.password_changed';
    case UserProfileUpdated = 'user.profile_updated';
    case UserTwoFactorReset = 'user.two_factor_reset';
    case UserTwoFactorConfigured = 'user.two_factor_configured';
    case UserActivated = 'user.activated';

    public function area(): AuditArea
    {
        return str_starts_with($this->value, 'access.') ? AuditArea::Access : AuditArea::Users;
    }

    /**
     * Access events record the IP address of the request (M10 §4.2), the others never do.
     */
    public function recordsIpAddress(): bool
    {
        return $this->area() === AuditArea::Access;
    }

    /**
     * Italian label (lang/it/audit.php).
     */
    public function label(): string
    {
        $label = __('audit.actions.'.str_replace('.', '_', $this->value));

        return is_string($label) ? $label : $this->value;
    }
}
