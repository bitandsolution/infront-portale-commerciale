<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Services\Notifications;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Weekly retention of the register of the e-mails (D-209; progettazione-tecnica §5.5.5): rows older
 * than 12 months. Scheduled in routes/console.php, can also be run by hand.
 */
class NotificationsPruneCommand extends Command
{
    /** @var string */
    protected $signature = 'notifications:prune';

    /** @var string */
    protected $description = 'Delete the rows of the e-mail register older than 12 months';

    public function handle(Notifications $notifications): int
    {
        $deleted = $notifications->prune();

        $this->line(Text::get('mail.prune.done', [
            'deleted' => (string) $deleted,
            'months' => (string) Notifications::RETENTION_MONTHS,
        ]));

        return self::SUCCESS;
    }
}
