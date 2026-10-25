<?php

namespace App\Console\Commands;

use App\Domain\Operations\Data\HealthResult;
use App\Domain\Operations\Services\HealthCheck;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Internal health with the details of each check (progettazione-tecnica §5.2.7; PR-09): the same
 * checks as the public endpoint /health, which answers only "ok" or "fail". Exit code 1 when a check
 * fails, so it can be used in scripts.
 */
class HealthCheckCommand extends Command
{
    /** @var string */
    protected $signature = 'health:check';

    /** @var string */
    protected $description = 'Show the internal health of the portal, check by check';

    public function handle(HealthCheck $health): int
    {
        $results = $health->run();

        foreach ($results as $result) {
            $this->line(Text::get('operations.health.line', [
                'status' => Text::get($result->ok ? 'operations.health.status_ok' : 'operations.health.status_fail'),
                'check' => Text::get('operations.health.checks.'.$result->check),
                'detail' => $result->detail,
            ]));
        }

        $healthy = HealthCheck::healthy($results);
        $this->line(Text::get($healthy ? 'operations.health.summary_ok' : 'operations.health.summary_fail', [
            'failed' => (string) count(array_filter($results, fn (HealthResult $result): bool => ! $result->ok)),
        ]));

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
