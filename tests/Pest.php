<?php

use App\Domain\Files\Services\FileStorage;
use App\Enums\AccessLinkType;
use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Mail\AccessLinkMail;
use App\Models\AuditEvent;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
| Pest configuration. Feature tests run on MariaDB 11.8 (CLAUDE.md §11), each in a transaction.
|
| Concurrency tests (PR-04) need two real database sessions that see each other's committed rows,
| so they cannot run inside the transaction of RefreshDatabase: they truncate the tables instead.
| The same holds for the database tests (PR-05), which connect as other MariaDB users. TRUNCATE
| does not fire the triggers of audit_events, so committed audit events are cleaned up too.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency', 'Database');

/*
| Tests of the document pipeline (fetta S1, D-308): they run Chromium, qpdf and poppler-utils, so
| they form the group "pdf", run by the CI job "pdf" and excluded from the backend job. They need no
| database.
*/

pest()->extend(TestCase::class)
    ->group('pdf')
    ->in('Pdf');

/*
| Helpers of the login tests (PR-03).
*/

/**
 * Current TOTP code of a key: the code the authenticator app would show now.
 */
function totpCode(string $secret = UserFactory::TOTP_SECRET): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

/*
| Helpers of the user management tests (PR-04).
*/

/**
 * Password::defaults() asks the breached-password archive (k-anonymity): no real request in
 * tests, the archive answers that no password is breached.
 */
function fakePasswordArchive(): void
{
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
}

/**
 * URL of the last access link e-mailed to an address (Mail::fake() must be active; since PR-08 the
 * e-mails are queued).
 */
function sentAccessLink(string $email, ?AccessLinkType $type = null): string
{
    $url = '';

    Mail::assertQueued(AccessLinkMail::class, function (AccessLinkMail $mail) use ($email, $type, &$url): bool {
        if (! $mail->hasTo($email) || ($type !== null && $mail->type !== $type)) {
            return false;
        }

        $url = $mail->url;

        return true;
    });

    return $url;
}

/*
| Helpers of the audit tests (PR-05).
*/

/**
 * Audit events of an action, oldest first.
 *
 * @return Collection<int, AuditEvent>
 */
function auditEventsOf(AuditAction $action): Collection
{
    return AuditEvent::query()->where('action', $action->value)->orderBy('id')->get();
}

/**
 * The only audit event of an action (fails when there is none or more than one).
 */
function soleAuditEvent(AuditAction $action): AuditEvent
{
    return AuditEvent::query()->where('action', $action->value)->sole();
}

/*
| Helpers of the file tests (PR-06). Storage::fake(StoredFile::DISK) must be active.
*/

/**
 * Stores a file through the service, inside a transaction as the domain Actions do. Without a
 * content, a 600×300 PNG drawn with GD.
 */
function storeTestFile(
    FilePurpose $purpose = FilePurpose::ClientLogo,
    ?User $author = null,
    ?string $contents = null,
    ?string $name = 'logo-prova.png',
): StoredFile {
    return DB::transaction(fn (): StoredFile => app(FileStorage::class)->storeContents(
        $contents ?? SampleFiles::png(600, 300),
        $purpose,
        $author,
        $name,
    ));
}

/*
| Helpers of the calendar import tests (F1-04): real .xlsx files written with OpenSpout (D-316).
*/

/**
 * An .xlsx file with one sheet: the given rows, the first one being the header. Cells are strings,
 * numbers (Excel dates and times as numbers) or null.
 *
 * @param  list<list<string|int|float|null>>  $rows
 */
function calendarFile(array $rows, string $name = 'calendario-prova.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cal').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();
    $contents = (string) file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent($name, $contents);
}
