<?php

namespace App\Console\Commands;

use App\Models\StoredFile;
use App\Support\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the files of the private disk that no stored_files row describes (progettazione-tecnica
 * §5.5.5). FileStorage already deletes the file of a rolled back transaction; this command removes
 * what is left when a process dies between the write and the commit.
 *
 * Only files older than 24 hours: a file just written may belong to a transaction still running.
 * Rows that no entity points to are not touched here: their clean-up comes with the slices that
 * use the files (F1, F3).
 *
 * Every night at 3:45, Rome time (routes/console.php).
 */
final class FilesPruneOrphansCommand extends Command
{
    /** Age of the files the command may delete. */
    public const GRACE_HOURS = 24;

    protected $signature = 'files:prune-orphans
        {--dry-run : Lists the orphan files without deleting them}';

    protected $description = 'Deletes the files of the private disk without a stored_files row';

    public function handle(): int
    {
        $disk = Storage::disk(StoredFile::DISK);
        $cutoff = now()->subHours(self::GRACE_HOURS)->getTimestamp();
        $orphans = [];

        $paths = array_values(array_filter(
            $disk->allFiles(),
            fn (string $path): bool => ! str_starts_with(basename($path), '.'),
        ));

        foreach (array_chunk($paths, 500) as $chunk) {
            $known = StoredFile::query()
                ->where('disk', StoredFile::DISK)
                ->whereIn('path', $chunk)
                ->pluck('path')
                ->all();

            foreach (array_diff($chunk, $known) as $path) {
                if ($disk->lastModified($path) < $cutoff) {
                    $orphans[] = $path;
                }
            }
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($orphans as $path) {
            $this->line(Text::get('files.console.prune_file', ['path' => $path]));

            if (! $dryRun) {
                $disk->delete($path);
            }
        }

        $this->line(Text::get($dryRun ? 'files.console.prune_dry_run' : 'files.console.prune_done', [
            'count' => (string) count($orphans),
        ]));

        return self::SUCCESS;
    }
}
