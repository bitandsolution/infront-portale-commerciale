<?php

namespace App\Console\Development;

use App\Domain\Documents\Data\LayoutPage;
use App\Domain\Documents\Data\PdfFont;
use App\Domain\Documents\Data\PdfMetadata;
use App\Domain\Documents\Data\PdfReport;
use App\Domain\Documents\Services\DocumentFonts;
use App\Domain\Documents\Services\PdfPipeline;
use App\Domain\Files\Services\FileStorage;
use App\Enums\FilePurpose;
use App\Models\StoredFile;
use App\Support\Documents\SampleDeck;
use App\Support\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Development only (fetta S1, D-309, D-311): generates a deck of about 40 slides with the PDF
 * pipeline and reports time, weight and checks (M09-CA4). The PDF is stored for real (author
 * "system") and the admin downloads it at /file/{id}; PNG previews of the recap pages stay in the
 * folder of the run, to judge the rendering with Interstate on the VM.
 *
 * Registered only when APP_ENV is local or testing (AppServiceProvider): it does not exist in
 * production.
 */
final class DocumentsTryCommand extends Command
{
    /**
     * M09-CA4: a deck of about 40 slides weighs at most 15 MB.
     */
    public const MAX_DECK_BYTES = 15 * 1024 * 1024;

    protected $signature = 'documents:try
        {--pages=40 : Number of pages of the deck}
        {--samples : Use the real images of DOCUMENT_SAMPLES_PATH instead of the generated ones}';

    protected $description = 'Tries the PDF pipeline with a sample deck and reports time, weight and checks (development only)';

    public function handle(PdfPipeline $pipeline, DocumentFonts $fonts, FileStorage $storage): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error(Text::get('documents.console.try_only_local'));

            return self::FAILURE;
        }

        $option = $this->option('pages');
        $pages = is_numeric($option) ? max(1, (int) $option) : 40;
        $samples = null;

        if ($this->option('samples') === true) {
            $samples = config('documents.samples_path');

            if (! is_string($samples) || SampleDeck::sampleImages($samples) === []) {
                $this->error(Text::get('documents.console.try_samples_missing'));

                return self::FAILURE;
            }
        }

        $folder = storage_path('app/document-try/'.now()->format('Ymd-His'));
        File::ensureDirectoryExists($folder);

        $this->line(Text::get('documents.console.try_intro', [
            'pages' => (string) $pages,
            'fonts' => Text::get($fonts->usesSubstitute() ? 'documents.console.try_fonts_substitute' : 'documents.console.try_fonts_interstate'),
            'images' => $samples === null
                ? Text::get('documents.console.try_images_generated')
                : Text::get('documents.console.try_images_samples', ['path' => $samples]),
        ]));

        $deck = SampleDeck::deck($folder.'/sorgenti', $pages, $samples);
        $report = $pipeline->render($deck, new PdfMetadata('Infront Proposta – prova della pipeline PDF', 'Stagione 2027-28'), $folder.'/prova-pipeline.pdf');

        $previews = 0;

        foreach ($deck as $page) {
            if ($page instanceof LayoutPage) {
                $pipeline->screenshot($page, $folder.sprintf('/anteprima-recap-%d.png', ++$previews));
            }
        }

        $pipeline->screenshot($deck[0], $folder.'/anteprima-copertina.png');

        $file = DB::transaction(fn (): StoredFile => $storage->storeContents(
            (string) file_get_contents($report->path),
            FilePurpose::GeneratedPdf,
            null,
            'prova-pipeline.pdf',
        ));

        $this->table(
            [Text::get('documents.console.try_columns.measure'), Text::get('documents.console.try_columns.value')],
            $this->rows($report),
        );

        $size = $this->megabytes($report->bytes);
        $this->line(Text::get($report->bytes <= self::MAX_DECK_BYTES ? 'documents.console.try_ca4_ok' : 'documents.console.try_ca4_ko', ['size' => $size]));

        foreach ($report->warnings as $warning) {
            $this->line(Text::get('documents.console.try_warning', ['warning' => $warning]));
        }

        $this->line(Text::get('documents.console.try_pdf', ['url' => route('files.download', $file)]));
        $this->line(Text::get('documents.console.try_previews', ['path' => $folder]));

        return $report->bytes <= self::MAX_DECK_BYTES ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<array{string, string}>
     */
    private function rows(PdfReport $report): array
    {
        $rows = [];

        foreach (['compose', 'render', 'optimize', 'check'] as $step) {
            $rows[] = [
                Text::get('documents.console.try_measures.'.$step),
                Text::get('documents.console.try_ms', ['value' => number_format($report->timings[$step] ?? 0, 0, ',', '.')]),
            ];
        }

        $fonts = array_map(fn (PdfFont $font): string => $font->name, $report->fonts);

        return [
            ...$rows,
            [Text::get('documents.console.try_measures.total'), Text::get('documents.console.try_ms', ['value' => number_format($report->totalMilliseconds(), 0, ',', '.')])],
            [Text::get('documents.console.try_measures.pages'), (string) $report->pages],
            [Text::get('documents.console.try_measures.size'), Text::get('documents.console.try_mb', ['value' => $this->megabytes($report->bytes)])],
            [Text::get('documents.console.try_measures.linearized'), Text::get('documents.console.try_yes')],
            [Text::get('documents.console.try_measures.fonts'), implode(', ', $fonts)],
        ];
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1, ',', '.');
    }
}
