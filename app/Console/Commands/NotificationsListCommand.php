<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Support\Text;
use App\Support\Users\UserPresenter;
use Illuminate\Console\Command;

/**
 * Read-only list of the latest e-mails of the register (M01 §10; D-209; PR-08), from the server:
 * to check the e-mails during the acceptance tests and, in production, through SSH. A page of the
 * portal for the admin is planned with slice F8. The command never changes a row.
 */
class NotificationsListCommand extends Command
{
    /** @var string */
    protected $signature = 'notifications:list
        {--limit=20 : Number of e-mails, newest first (max 500)}
        {--status= : Only one state (queued, sent, failed)}
        {--user= : Only the e-mails sent to an address}';

    /** @var string */
    protected $description = 'List the latest e-mails of the register (read-only)';

    public function handle(): int
    {
        $query = NotificationLog::query()->orderByDesc('created_at')->orderByDesc('id');

        $status = $this->stringOption('status');
        if ($status !== null) {
            if (NotificationStatus::tryFrom($status) === null) {
                $this->line(Text::get('mail.list.unknown_status', [
                    'status' => $status,
                    'statuses' => implode(', ', array_map(fn (NotificationStatus $case): string => $case->value, NotificationStatus::cases())),
                ]));

                return self::FAILURE;
            }
            $query->where('status', $status);
        }

        $email = $this->stringOption('user');
        if ($email !== null) {
            $query->where('recipient_email', mb_strtolower($email));
        }

        $limit = (int) ($this->stringOption('limit') ?? '20');
        $logs = $query->limit(min(max($limit, 1), 500))->get();

        if ($logs->isEmpty()) {
            $this->line(Text::get('mail.list.empty'));

            return self::SUCCESS;
        }

        /** @var list<string> $columns */
        $columns = (array) __('mail.list.columns');

        $this->table($columns, $logs->map(fn (NotificationLog $log): array => [
            $log->created_at->setTimezone(UserPresenter::TIMEZONE)->format('d/m/Y H:i:s'),
            $log->type->label(),
            $log->recipient_email,
            $log->status->label(),
            (string) $log->attempts,
            $log->error ?? '',
        ])->all());

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
