<?php

namespace App\Console\Development;

use App\Support\Demo\DemoAssets;
use App\Support\Demo\DemoPreparer;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Data of the demo of 4/12/2026 (D-400, D-438…D-442; DM-08): on a database just created with
 * `migrate:fresh --seed` and the first admin, prepares season, clubs, calendar, price list,
 * capacities, clients, slide library, LED simulations and the three groups of proposals of the demo.
 * Registered only in local and testing (AppServiceProvider), never in production.
 */
class DemoPrepareCommand extends Command
{
    protected $signature = 'demo:prepare';

    protected $description = 'Prepara i dati della demo (solo sviluppo, docs/dev/prova-generale-demo.md)';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error(Text::get('demo.console.only_local'));

            return self::FAILURE;
        }

        $assets = DemoAssets::fromConfig();
        $preparer = new DemoPreparer($assets);

        if (($refusal = $preparer->check()) !== null) {
            $this->error($refusal);

            return self::FAILURE;
        }

        $this->line(Text::get('demo.console.intro', [
            'assets' => $assets->root() ?? Text::get('demo.console.assets_none'),
        ]));

        $report = $preparer->prepare(function (string $step): void {
            $this->line($step);
        });

        foreach ($report as $line) {
            $this->line('- '.$line);
        }

        $this->line(Text::get('demo.console.done'));

        return self::SUCCESS;
    }
}
