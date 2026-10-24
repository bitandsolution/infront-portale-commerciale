<?php

namespace App\Console\Development;

use App\Domain\Files\Exceptions\InvalidFile;
use App\Domain\Files\Services\FileStorage;
use App\Enums\FilePurpose;
use App\Models\StoredFile;
use App\Support\Files\SampleFiles;
use App\Support\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Development only (PR-06): tries the storage service with files generated on the spot, so the
 * checks can be seen on the VM before any upload page exists. The accepted files are stored for
 * real (author "system") and can be downloaded by the admin at /file/{id}.
 *
 * Registered only when APP_ENV is local or testing (AppServiceProvider): it does not exist in
 * production.
 */
final class FilesTryCommand extends Command
{
    protected $signature = 'files:try';

    protected $description = 'Tries the checks of the stored files with generated samples (development only)';

    public function handle(FileStorage $storage): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error(Text::get('files.console.try_only_local'));

            return self::FAILURE;
        }

        $this->line(Text::get('files.console.try_intro'));

        $rows = [];
        $asExpected = 0;
        $cases = $this->cases();

        foreach ($cases as $key => [$purpose, $contents, $name, $accept]) {
            [$file, $error] = $this->tryCase($storage, $purpose, $contents, $name);
            $ok = ($file !== null) === $accept;
            $asExpected += $ok ? 1 : 0;

            $rows[] = [
                Text::get("files.console.try_cases.{$key}"),
                Text::get($file !== null ? 'files.console.try_accepted' : 'files.console.try_refused')
                    .' ('.Text::get($ok ? 'files.console.try_expected_ok' : 'files.console.try_expected_ko').')',
                $file !== null ? 'id '.$file->id : (string) $error,
                $file?->mime_type ?? '',
                $file !== null && $file->width !== null ? "{$file->width}×{$file->height}" : '',
                $file !== null ? (string) $file->size_bytes : (string) strlen($contents),
                $file !== null ? substr($file->sha256, 0, 12) : '',
            ];
        }

        $columns = ['case', 'outcome', 'detail', 'mime', 'pixels', 'size', 'sha256'];
        $this->table(array_map(fn (string $column): string => Text::get("files.console.try_columns.{$column}"), $columns), $rows);
        $this->line(Text::get('files.console.try_summary', ['ok' => (string) $asExpected, 'total' => (string) count($cases)]));

        return $asExpected === count($cases) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{0: StoredFile|null, 1: string|null}
     */
    private function tryCase(FileStorage $storage, FilePurpose $purpose, string $contents, string $name): array
    {
        try {
            $file = DB::transaction(fn (): StoredFile => $storage->storeContents($contents, $purpose, null, $name));

            return [$file, null];
        } catch (InvalidFile $invalid) {
            return [null, $invalid->getMessage()];
        }
    }

    /**
     * Key => [purpose, content, name chosen by the "user", accepted?].
     *
     * @return array<string, array{FilePurpose, string, string, bool}>
     */
    private function cases(): array
    {
        $megabyte = 1024 * 1024;

        return [
            'logo_png' => [FilePurpose::ClientLogo, SampleFiles::png(600, 300, 'Autore: prova'), 'logo-prova.png', true],
            'logo_jpeg_exif' => [FilePurpose::ClientLogo, SampleFiles::jpeg(600, 300, exif: true), 'logo-prova.jpg', true],
            'logo_polyglot' => [FilePurpose::ClientLogo, SampleFiles::png(300, 100).SampleFiles::php(), 'logo-con-php.png', true],
            'logo_3mb' => [FilePurpose::ClientLogo, SampleFiles::padded(SampleFiles::png(600, 300), 3 * $megabyte), 'logo-pesante.png', false],
            'logo_pdf' => [FilePurpose::ClientLogo, SampleFiles::pdf(), 'logo.pdf', false],
            'logo_php' => [FilePurpose::ClientLogo, SampleFiles::php(), 'logo.png', false],
            'logo_html' => [FilePurpose::ClientLogo, SampleFiles::html(), 'logo.png', false],
            'logo_svg' => [FilePurpose::ClientLogo, SampleFiles::svg(), 'logo.svg', false],
            'slide_small' => [FilePurpose::SlideImage, SampleFiles::png(1600, 900), 'slide-piccola.png', false],
            'slide_ok' => [FilePurpose::SlideImage, SampleFiles::png(1920, 1080), 'slide-prova.png', true],
            'slide_jpeg' => [FilePurpose::SlideImage, SampleFiles::jpeg(1920, 1080), 'slide-prova.jpg', false],
            'xlsx_ok' => [FilePurpose::CalendarImport, SampleFiles::xlsx(), 'calendario-prova.xlsx', true],
            'xlsm' => [FilePurpose::CalendarImport, SampleFiles::xlsx(macros: true), 'calendario-macro.xlsm', false],
            'xls' => [FilePurpose::CalendarImport, SampleFiles::xls(), 'calendario-vecchio.xls', false],
            'pdf_ok' => [FilePurpose::GeneratedPdf, SampleFiles::pdf(), 'documento-prova.pdf', true],
        ];
    }
}
