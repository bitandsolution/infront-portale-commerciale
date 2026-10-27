<?php

namespace App\Console\Commands;

use App\Enums\CalendarImportStatus;
use App\Models\CalendarImport;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Discards the calendar imports left in preview (FL-02; slice F1-04): an import not touched for
 * 7 days is closed as "discarded" and its preview, which can be large, is cleared. Nothing changes in
 * the calendar; the file stays in the archive (C10). Confirmed imports are never touched.
 *
 * Every night at 3:30, Rome time (routes/console.php).
 */
final class CalendarImportsPruneCommand extends Command
{
    /** Days after which an untouched preview is discarded. */
    public const STALE_DAYS = 7;

    protected $signature = 'calendar-imports:prune';

    protected $description = 'Discards the calendar imports left in preview for more than 7 days';

    public function handle(): int
    {
        $discarded = CalendarImport::query()
            ->where('status', CalendarImportStatus::Preview->value)
            ->where('updated_at', '<', now()->subDays(self::STALE_DAYS))
            ->update(['status' => CalendarImportStatus::Discarded->value, 'preview' => null, 'updated_at' => now()]);

        $this->line(Text::get('sports.console.imports_pruned', ['count' => (string) $discarded]));

        return self::SUCCESS;
    }
}
