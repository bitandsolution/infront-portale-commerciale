<?php

namespace App\Console\Commands;

use App\Domain\Audit\Services\AuditRetention;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Nightly retention of the audit trail (RB-A2, M10-CA7; D-210, D-211): scheduled in
 * routes/console.php, can also be run by hand.
 */
class AuditPruneCommand extends Command
{
    /** @var string */
    protected $signature = 'audit:prune';

    /** @var string */
    protected $description = 'Delete audit events older than 5 years and clear expired IP addresses';

    public function handle(AuditRetention $retention): int
    {
        $result = $retention->prune();

        $this->line(Text::get('audit.prune.done', [
            'deleted' => (string) $result['deleted'],
            'ip_cleared' => (string) $result['ip_cleared'],
            'days' => (string) AuditRetention::ipRetentionDays(),
        ]));

        return self::SUCCESS;
    }
}
