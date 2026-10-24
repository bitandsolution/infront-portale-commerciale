<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Data\AuditSubject;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\SupportCode;

/**
 * Writes the audit events (M10 §4; D-127, D-128, D-283). The only writer of audit_events.
 *
 * - Every event carries: date and time (UTC, microseconds), author with name and role copied at
 *   that moment, area, action, object, values before and after, reason, request code.
 * - request_id is the support code of the request (D-265): the code shown with a system error leads
 *   to the events of the same request. A server command has one code for its whole run.
 * - The IP address is recorded only for the access events (M10 §4.2).
 * - Values before and after: only the fields passed by the caller, and never a field that holds
 *   a password, a hash, a token or a secret of the second factor, even if passed by mistake.
 *
 * Domain actions call it inside their own transaction: an action that fails leaves no event, and
 * an event that cannot be written cancels the action (fail-closed, D-283).
 */
final class AuditLog
{
    /** Fields that never reach old_values / new_values. */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'code',
        'recovery_code',
    ];

    public function __construct(private readonly SupportCode $supportCode) {}

    /**
     * Event of a domain action. A null author is the system (server command).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(
        AuditAction $action,
        ?User $actor,
        ?AuditSubject $subject = null,
        array $old = [],
        array $new = [],
        ?string $reason = null,
    ): AuditEvent {
        return $this->write($action, $actor, $actor === null, $subject, $old, $new, $reason);
    }

    /**
     * Access event (D-128): login, logout, lockout, second factor, end of the session. The user is
     * the one who signed in or whose account was attempted; null when the e-mail matches nobody.
     */
    public function access(AuditAction $action, ?User $user, ?AuditSubject $subject = null, ?string $reason = null): AuditEvent
    {
        return $this->write($action, $user, false, $subject ?? ($user !== null ? AuditSubject::user($user) : null), [], [], $reason);
    }

    /**
     * Only the fields whose value changed, as [before, after].
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function changes(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $field => $value) {
            if (! array_key_exists($field, $before) || $before[$field] !== $value) {
                $old[$field] = $before[$field] ?? null;
                $new[$field] = $value;
            }
        }

        return [$old, $new];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function write(
        AuditAction $action,
        ?User $user,
        bool $bySystem,
        ?AuditSubject $subject,
        array $old,
        array $new,
        ?string $reason,
    ): AuditEvent {
        $event = new AuditEvent([
            'occurred_at' => now(),
            'user_id' => $user?->id,
            'user_name' => $bySystem ? AuditEvent::SYSTEM : $user?->fullName(),
            'user_role' => $bySystem ? AuditEvent::SYSTEM : $user?->role->value,
            'area' => $action->area()->value,
            'action' => $action->value,
            'object_type' => $subject?->type,
            'object_id' => $subject?->id,
            'object_label' => $subject?->label,
            'old_values' => self::clean($old),
            'new_values' => self::clean($new),
            'reason' => $reason !== null ? mb_substr($reason, 0, 1000) : null,
            'ip_address' => $action->recordsIpAddress() ? $this->ipAddress() : null,
            'request_id' => $this->supportCode->value(),
        ]);

        $event->save();

        return $event;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private static function clean(array $values): ?array
    {
        $values = array_diff_key($values, array_flip(self::SENSITIVE_FIELDS));

        return $values === [] ? null : $values;
    }

    private function ipAddress(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $ip = request()->ip();

        return $ip !== null ? mb_substr($ip, 0, 45) : null;
    }
}
