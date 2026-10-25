<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Data\DocumentPage;
use App\Domain\Documents\Data\LayoutPage;
use App\Domain\Documents\Data\PdfFont;
use App\Domain\Documents\Data\PdfMetadata;
use App\Domain\Documents\Data\PdfReport;
use App\Domain\Documents\Data\StaticSlidePage;
use App\Domain\Documents\Exceptions\DocumentCheckFailed;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Enums\DocumentLayout;
use App\Support\Text;
use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/**
 * Pipeline of the PDF documents (progettazione-tecnica §5.5.2, D-220; fetta S1, D-309…D-312):
 *
 * 1. folder of the job: a new folder for each generation, removed at the end;
 * 2. images: slides and backgrounds as JPEG quality 85 (D-310), logos copied next to the page;
 * 3. HTML: one 1920×1080 page per slide (resources/views/documents);
 * 4. Chromium headless without network (Chromium, D-312) → PDF;
 * 5. qpdf: linearisation, object streams, compression, title / author / subject (M09 §7);
 * 6. checks (M09 §9): valid PDF, number of pages, fonts embedded as subsets, warning beyond 20 MB.
 *
 * The composition of the document (which slides, in which order, from which proposal) is slice F7:
 * here the pages arrive ready. The same code will serve the simulator of M08 (D-220).
 */
final class PdfPipeline
{
    /**
     * M09 §9: a PDF heavier than this is generated anyway, with a warning.
     */
    public const WARNING_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly Chromium $chromium,
        private readonly DocumentFonts $fonts,
        private readonly SlideImages $images,
        private readonly TextFitter $fitter,
        private readonly PdfTools $tools,
    ) {}

    /**
     * Renders the pages into an optimised PDF at $output and checks it.
     *
     * @param  list<DocumentPage>  $pages
     */
    public function render(array $pages, PdfMetadata $metadata, string $output): PdfReport
    {
        if ($pages === []) {
            throw new LogicException('A document needs at least one page.');
        }

        $timings = [];
        $folder = $this->jobFolder();

        try {
            $start = microtime(true);
            $html = $this->html($pages, $metadata->title, $folder);
            $timings['compose'] = self::elapsed($start);

            $start = microtime(true);
            $raw = $folder.'/raw.pdf';
            $this->chromium->browsershot($html)
                ->paperSize(1920, 1080, 'px')
                ->margins(0, 0, 0, 0)
                ->showBackground()
                ->savePdf($raw);
            $timings['render'] = self::elapsed($start);

            $start = microtime(true);
            File::ensureDirectoryExists(dirname($output));
            $this->tools->optimize($raw, $output, $metadata);
            $timings['optimize'] = self::elapsed($start);

            $start = microtime(true);
            [$pageCount, $fonts, $warnings] = $this->check($output, count($pages));
            $timings['check'] = self::elapsed($start);

            return new PdfReport($output, $pageCount, (int) filesize($output), $timings, $fonts, $warnings);
        } finally {
            File::deleteDirectory($folder);
        }
    }

    /**
     * PNG of a single page at 1920×1080, as the PDF would show it: used by the visual regression
     * tests and to look at a layout (D-311).
     */
    public function screenshot(DocumentPage $page, string $output): void
    {
        $folder = $this->jobFolder();

        try {
            File::ensureDirectoryExists(dirname($output));
            $this->chromium->browsershot($this->html([$page], 'Anteprima', $folder))
                ->windowSize(1920, 1080)
                ->deviceScaleFactor(1)
                ->save($output);
        } finally {
            File::deleteDirectory($folder);
        }
    }

    /**
     * Writes the HTML of the pages with their images into the folder of the job.
     *
     * @param  list<DocumentPage>  $pages
     */
    private function html(array $pages, string $title, string $folder): string
    {
        $converted = [];
        $copied = [];

        $image = function (string $source) use ($folder, &$converted): string {
            if (! isset($converted[$source])) {
                $name = sprintf('image-%03d.jpg', count($converted) + 1);
                $this->images->toJpeg($source, $folder.'/'.$name);
                $converted[$source] = $name;
            }

            return $converted[$source];
        };

        $asset = function (string $source) use ($folder, &$copied): string {
            if (! isset($copied[$source])) {
                $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'png';
                $name = sprintf('asset-%03d.%s', count($copied) + 1, $extension);

                if (! copy($source, $folder.'/'.$name)) {
                    throw new RuntimeException("Cannot copy {$source} into the folder of the document.");
                }

                $copied[$source] = $name;
            }

            return $copied[$source];
        };

        $views = [];

        foreach ($pages as $page) {
            $views[] = match (true) {
                $page instanceof StaticSlidePage => ['image' => $image($page->imagePath), 'view' => null, 'data' => []],
                $page instanceof LayoutPage => [
                    'image' => $image($page->backgroundPath),
                    'view' => $page->layout->view(),
                    'data' => $this->layoutData($page, $asset),
                ],
                default => throw new LogicException('Unknown page type '.$page::class.'.'),
            };
        }

        $path = $folder.'/document.html';

        file_put_contents($path, view('documents.document', [
            'title' => $title,
            'fontsCss' => $this->fonts->css(),
            'fontFamily' => DocumentFonts::FAMILY,
            'pages' => $views,
        ])->render());

        return $path;
    }

    /**
     * @param  Closure(string): string  $asset
     * @return array<string, mixed>
     */
    private function layoutData(LayoutPage $page, Closure $asset): array
    {
        return match ($page->layout) {
            DocumentLayout::NetworkRecap => NetworkRecapLayout::viewData($page->data, $this->fitter, $asset),
        };
    }

    /**
     * @return array{int, list<PdfFont>, list<string>} pages, fonts, warnings
     */
    private function check(string $pdf, int $expectedPages): array
    {
        $this->tools->check($pdf);

        $pages = $this->tools->pageCount($pdf);

        if ($pages !== $expectedPages) {
            throw DocumentCheckFailed::pageCount($pages, $expectedPages);
        }

        if (! $this->tools->isLinearized($pdf)) {
            throw DocumentCheckFailed::notLinearized();
        }

        $fonts = $this->tools->fonts($pdf);

        foreach ($fonts as $font) {
            if (! $font->embedded || ! $font->subset) {
                throw DocumentCheckFailed::fontNotEmbedded($font->name);
            }
        }

        $bytes = (int) filesize($pdf);
        $warnings = [];

        // A font that is not the document font means a character missing from it (for example "%"
        // in Interstate): Chromium drew it with a system font.
        $family = $this->fonts->usesSubstitute() ? 'DejaVuSans' : 'Interstate';

        foreach ($fonts as $font) {
            if (! str_contains($font->name, $family)) {
                $warnings[] = Text::get('documents.warnings.fallback_font', ['font' => preg_replace('/^[A-Z]{6}\+/', '', $font->name) ?? $font->name]);
            }
        }

        if ($bytes > self::WARNING_BYTES) {
            $warnings[] = Text::get('documents.warnings.too_heavy', ['size' => number_format($bytes / 1048576, 1, ',', '.')]);
        }

        return [$pages, $fonts, $warnings];
    }

    private function jobFolder(): string
    {
        $base = config('documents.work_path');
        $folder = (is_string($base) && $base !== '' ? rtrim($base, '/') : storage_path('framework/documents')).'/'.Str::ulid();
        File::ensureDirectoryExists($folder, 0700);

        return $folder;
    }

    /**
     * Milliseconds since $start (a microtime(true) value).
     */
    private static function elapsed(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
