<?php

namespace App\Models;

use App\Domain\Audit\Exceptions\AuditEventImmutable;
use App\Models\Builders\AuditEventBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Event of the audit trail (M10 §4; D-127…D-129, D-210, D-211). Written only by
 * App\Domain\Audit\Services\AuditLog.
 *
 * Append-only (RB-A1): an event is inserted once and never changed nor deleted by the code; the
 * database triggers refuse UPDATE and DELETE too (M10-CA3). The only deletions and changes are
 * those of the retention (audit:prune): events older than 5 years, IP addresses older than
 * config('audit.ip_retention_days').
 *
 * user_id is null for the system (server commands) and for a failed login with an unknown e-mail;
 * user_name and user_role are the values when the event happened (M10 §8). For the system they hold
 * the code "system" (AuditEvent::SYSTEM).
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $user_role
 * @property string $area
 * @property string $action
 * @property string|null $object_type
 * @property int|null $object_id
 * @property string|null $object_label
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $request_id
 */
final class AuditEvent extends Model
{
    /** Retention of the events (RB-A2, D-128), also enforced by the delete trigger. */
    public const RETENTION_YEARS = 5;

    /** user_name and user_role of the events written by the system (server commands). */
    public const SYSTEM = 'system';

    public $timestamps = false;

    /** Microseconds keep the order of the events written by the same request. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @param  QueryBuilder  $query
     * @return AuditEventBuilder<static>
     */
    public function newEloquentBuilder($query): AuditEventBuilder
    {
        return new AuditEventBuilder($query);
    }

    /**
     * Inserts the event; an event already saved is never saved again.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw AuditEventImmutable::make();
        }

        return parent::save($options);
    }

    /**
     * @return bool|null
     */
    public function delete()
    {
        throw AuditEventImmutable::make();
    }
}
