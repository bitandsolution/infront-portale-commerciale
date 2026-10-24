<?php

use App\Enums\FilePurpose;
use App\Models\StoredFile;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
| Server commands of the files (PR-06): clean-up of the orphan files (progettazione-tecnica §5.5.5)
| and the development check files:try.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

/**
 * Writes a file without a stored_files row, with the given age.
 */
function filesOrphan(string $path, int $hoursOld): string
{
    $disk = Storage::disk(StoredFile::DISK);
    $disk->put($path, 'resto di una transazione interrotta');
    touch($disk->path($path), now()->subHours($hoursOld)->getTimestamp());

    return $path;
}

it('C10: deletes the files without a row older than 24 hours, keeps the recent ones and the stored files', function () {
    $old = filesOrphan('client_logo/2026/10/orfano-vecchio.png', 25);
    $recent = filesOrphan('client_logo/2026/10/orfano-recente.png', 2);
    $stored = storeTestFile(FilePurpose::ClientLogo);
    touch(Storage::disk(StoredFile::DISK)->path($stored->path), now()->subDays(10)->getTimestamp());

    $this->artisan('files:prune-orphans')
        ->expectsOutputToContain('client_logo/2026/10/orfano-vecchio.png')
        ->expectsOutputToContain('File orfani eliminati: 1.')
        ->assertSuccessful();

    $disk = Storage::disk(StoredFile::DISK);
    expect($disk->exists($old))->toBeFalse()
        ->and($disk->exists($recent))->toBeTrue()
        ->and($disk->exists($stored->path))->toBeTrue();
});

it('C10: with --dry-run lists the orphan files without deleting them', function () {
    $old = filesOrphan('slide_image/2026/10/orfano.png', 48);

    $this->artisan('files:prune-orphans', ['--dry-run' => true])
        ->expectsOutputToContain('File orfani da eliminare (prova, nessuna cancellazione): 1.')
        ->assertSuccessful();

    expect(Storage::disk(StoredFile::DISK)->exists($old))->toBeTrue();
});

it('§5.5.5: cleans the orphan files every night at 3:45, Rome time, without overlapping', function () {
    /** @var ScheduledEvent|null $task */
    $task = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'files:prune-orphans'));

    expect($task)->not->toBeNull()
        ->and($task?->expression)->toBe('45 3 * * *')
        ->and($task?->timezone)->toBe('Europe/Rome')
        ->and($task?->withoutOverlapping)->toBeTrue();
});

it('PR-06: files:try stores the valid samples and refuses the others, as expected', function () {
    $status = Artisan::call('files:try');
    $output = Artisan::output();

    expect($status)->toBe(0)
        ->and($output)->toContain('Casi come previsto: 15 su 15.')
        ->and($output)->toContain('Il file pesa 3 MB: il massimo è 2 MB.')
        ->and($output)->toContain('deve essere esattamente 1920×1080')
        ->and($output)->toContain('Il file Excel contiene macro')
        ->and(StoredFile::query()->count())->toBe(6)
        ->and(StoredFile::query()->whereNotNull('created_by')->count())->toBe(0);
});
