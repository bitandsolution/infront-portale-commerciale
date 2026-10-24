<?php

namespace App\Console\Commands;

use App\Enums\AccessReason;
use App\Enums\AuditAction;
use App\Enums\AuditArea;
use App\Models\AuditEvent;
use App\Models\Builders\AuditEventBuilder;
use App\Models\User;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Read-only list of the latest audit events, from the server (D-283): to check the audit during the
 * acceptance tests and, in production, through SSH. The audit page of the portal arrives with slice
 * F8 (M10 RB-A3, only the admin). The command never changes an event.
 */
class AuditListCommand extends Command
{
    /** @var string */
    protected $signature = 'audit:list
        {--limit=20 : Number of events, newest first (max 500)}
        {--area= : Only one area (access, users)}
        {--action= : Only one action, e.g. access.login_failed}
        {--user= : Only the events of a user (e-mail) or about him}';

    /** @var string */
    protected $description = 'List the latest audit events (read-only)';

    public function handle(): int
    {
        $query = AuditEvent::query()->orderByDesc('occurred_at')->orderByDesc('id');

        $area = $this->stringOption('area');
        if ($area !== null) {
            if (AuditArea::tryFrom($area) === null) {
                $this->line(Text::get('audit.list.unknown_area', ['area' => $area]));

                return self::FAILURE;
            }
            $query->where('area', $area);
        }

        $action = $this->stringOption('action');
        if ($action !== null) {
            if (AuditAction::tryFrom($action) === null) {
                $this->line(Text::get('audit.list.unknown_action', ['action' => $action]));

                return self::FAILURE;
            }
            $query->where('action', $action);
        }

        $email = $this->stringOption('user');
        if ($email !== null) {
            $email = mb_strtolower(trim($email));
            $userId = User::query()->where('email', $email)->value('id');

            // Events made by the user, about him, or failed logins with his e-mail typed.
            $query->where(
                /** @param AuditEventBuilder<AuditEvent> $events */
                function (AuditEventBuilder $events) use ($email, $userId): void {
                    $events->where('object_label', $email);

                    if (is_int($userId)) {
                        $events->orWhere('user_id', $userId)->orWhere(
                            /** @param AuditEventBuilder<AuditEvent> $about */
                            function (AuditEventBuilder $about) use ($userId): void {
                                $about->where('object_type', 'user')->where('object_id', $userId);
                            },
                        );
                    }
                },
            );
        }

        $limit = (int) ($this->stringOption('limit') ?? '20');
        $events = $query->limit(min(max($limit, 1), 500))->get();

        if ($events->isEmpty()) {
            $this->line(Text::get('audit.list.empty'));

            return self::SUCCESS;
        }

        $this->table(
            [
                Text::get('audit.list.columns.occurred_at'),
                Text::get('audit.list.columns.user'),
                Text::get('audit.list.columns.action'),
                Text::get('audit.list.columns.object'),
                Text::get('audit.list.columns.changes'),
                Text::get('audit.list.columns.reason'),
                Text::get('audit.list.columns.ip'),
                Text::get('audit.list.columns.request'),
            ],
            $events->map(fn (AuditEvent $event): array => [
                $event->occurred_at->setTimezone('Europe/Rome')->format('d/m/Y H:i:s'),
                self::author($event),
                $event->action,
                implode(' ', array_filter([$event->object_type, $event->object_id, $event->object_label], fn (mixed $part): bool => $part !== null && $part !== '')),
                self::changes($event),
                self::reason($event),
                $event->ip_address ?? '',
                $event->request_id ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function author(AuditEvent $event): string
    {
        if ($event->user_role === AuditEvent::SYSTEM) {
            return Text::get('audit.system');
        }

        if ($event->user_name === null) {
            return '—';
        }

        return $event->user_name.' ('.$event->user_role.', #'.$event->user_id.')';
    }

    private static function changes(AuditEvent $event): string
    {
        $parts = [];
        $old = $event->old_values ?? [];

        foreach ($event->new_values ?? [] as $field => $value) {
            $parts[] = array_key_exists($field, $old)
                ? $field.': '.self::scalar($old[$field]).' → '.self::scalar($value)
                : $field.': '.self::scalar($value);
        }

        return implode('; ', $parts);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => '∅',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    private static function reason(AuditEvent $event): string
    {
        if ($event->reason === null) {
            return '';
        }

        return $event->area === AuditArea::Access->value
            ? (AccessReason::tryFrom($event->reason)?->label() ?? $event->reason)
            : $event->reason;
    }
}
