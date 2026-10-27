<?php

namespace App\Console\Commands;

use App\Domain\Sports\Actions\ImportLegacyClubs;
use App\Domain\Sports\Data\LegacyClubReport;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Domain\Sports\Services\LegacyClubFile;
use App\Support\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-off creation of the clubs of the old system from the CSV extracted by Antonio (D-229, D-318,
 * D-329; slice F1-06). Rules in ImportLegacyClubs; query and steps in docs/dev/club-vecchio-sistema.md.
 *
 *   php artisan sports:import-legacy-clubs storage/app/club-idoc.csv --merge=21,35 --dry-run
 *
 * --dry-run runs everything and prints the report, then rolls back.
 */
final class SportsImportLegacyClubsCommand extends Command
{
    protected $signature = 'sports:import-legacy-clubs
        {file : CSV extracted from idoc__teams_list}
        {--merge=* : Ids of the old system that are the same club, separated by commas (e.g. 21,35)}
        {--dry-run : Shows the report without saving anything}';

    protected $description = 'Creates the clubs of the old system from the extracted CSV (one-off)';

    public function handle(LegacyClubFile $file, ImportLegacyClubs $import): int
    {
        $merges = [];

        foreach ((array) $this->option('merge') as $value) {
            $value = is_string($value) ? $value : '';

            if (preg_match('/^\d+(,\d+)+$/', $value) !== 1) {
                $this->error(Text::get('sports.legacy.merge_invalid', ['value' => $value]));

                return self::FAILURE;
            }

            $merges[] = array_map(intval(...), explode(',', $value));
        }

        $dryRun = (bool) $this->option('dry-run');
        $path = $this->argument('file');

        try {
            $rows = $file->read(is_string($path) ? $path : '');

            DB::beginTransaction();

            try {
                $report = $import->handle($rows, $merges);
            } catch (Throwable $exception) {
                DB::rollBack();

                throw $exception;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (SportsActionNotAllowed $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->print($report, $dryRun);

        return self::SUCCESS;
    }

    private function print(LegacyClubReport $report, bool $dryRun): void
    {
        if ($dryRun) {
            $this->warn(Text::get('sports.legacy.dry_run'));
        }

        $this->line(Text::get('sports.legacy.created', ['count' => (string) count($report->created)]));
        foreach ($report->created as $club) {
            $this->line('  + '.$club);
        }

        $this->line(Text::get('sports.legacy.existing', ['count' => (string) count($report->existing)]));
        foreach ($report->existing as $club) {
            $this->line('  = '.$club);
        }

        $this->line(Text::get('sports.legacy.aliases', ['count' => (string) $report->aliases]));
        $this->line(Text::get('sports.legacy.references', ['count' => (string) $report->references]));
        $this->line(Text::get('sports.legacy.anomalies', ['count' => (string) count($report->anomalies)]));
        foreach ($report->anomalies as $anomaly) {
            $this->warn('  ! '.$anomaly);
        }
    }
}
