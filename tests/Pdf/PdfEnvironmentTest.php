<?php

use App\Domain\Documents\Services\Chromium;
use App\Domain\Documents\Services\DocumentFonts;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/*
| Environment of the document pipeline (fetta S1-01, D-308, D-311): Chromium (chrome-headless-shell
| installed by `npm ci`), qpdf and poppler-utils really run. Group "pdf": the CI job "pdf" runs these
| tests on a runner with the tools; the backend job excludes them. On the VM they run after the
| rebuild of the Sail container (docs/dev/ambiente-locale.md).
|
| The fonts are the DejaVu substitute (phpunit.xml empties DOCUMENT_FONTS_PATH): the Interstate files
| never enter the repository nor CI (D-249); Antonio judges Interstate on the VM (S1-02).
*/

/**
 * One 1920×1080 page (D-112) with the three weights of the document font and the characters of the
 * layouts: €, Italian accents, typographic minutes and seconds.
 */
function pdfEnvironmentHtml(): string
{
    $fonts = app(DocumentFonts::class)->css();
    $family = DocumentFonts::FAMILY;

    return <<<HTML
        <!doctype html>
        <html lang="it">
        <head>
        <meta charset="utf-8">
        <style>
        {$fonts}
        @page { size: 1920px 1080px; margin: 0; }
        html, body { margin: 0; padding: 0; }
        .page { position: relative; width: 1920px; height: 1080px; overflow: hidden; background: #ffffff; font-family: '{$family}'; color: #333333; }
        .text { position: absolute; left: 113px; }
        </style>
        </head>
        <body>
        <div class="page">
            <div class="text" style="top: 200px; font-weight: 400; font-size: 32px;">Importi IVA esclusa · città, perché, più</div>
            <div class="text" style="top: 300px; font-weight: 700; font-size: 37.3px;">Totale / stagione 28' 30" · 1'00" · 1′30″</div>
            <div class="text" style="top: 420px; font-weight: 900; font-size: 46.7px;">107.996,00 € · 1,1 mln · 745 k</div>
        </div>
        </body>
        </html>
        HTML;
}

/**
 * Output of a command-line tool; the test fails with the tool's error if it exits with an error.
 *
 * @param  list<string>  $command
 */
function pdfEnvironmentRun(array $command): string
{
    $process = new Process($command);
    $process->mustRun();

    return $process->getOutput();
}

/**
 * Renders the test page to a PDF in the given folder and returns its path.
 */
function pdfEnvironmentRender(string $folder): string
{
    $page = $folder.'/environment.html';
    $pdf = $folder.'/environment.pdf';
    file_put_contents($page, pdfEnvironmentHtml());

    app(Chromium::class)
        ->browsershot($page)
        ->paperSize(1920, 1080, 'px')
        ->margins(0, 0, 0, 0)
        ->showBackground()
        ->savePdf($pdf);

    return $pdf;
}

beforeEach(function () {
    $this->pdfFolder = storage_path('framework/testing/pdf-environment');
    File::ensureDirectoryExists($this->pdfFolder);
});

afterEach(function () {
    File::deleteDirectory($this->pdfFolder);
});

it('S1: Chromium renders one 1920×1080 page (1440 × 810 pt)', function () {
    $info = pdfEnvironmentRun([config('documents.pdfinfo_binary'), pdfEnvironmentRender($this->pdfFolder)]);

    expect($info)->toMatch('/^Pages:\s+1$/m')
        ->and($info)->toMatch('/^Page size:\s+1440 x 810 pts/m');
});

it('M09 §7: the PDF embeds the document fonts as subsets', function () {
    $output = pdfEnvironmentRun([config('documents.pdffonts_binary'), pdfEnvironmentRender($this->pdfFolder)]);

    // pdffonts: two header lines, then one line per font: name, type, encoding, emb, sub, uni, id.
    $fonts = array_values(array_filter(array_slice(explode("\n", trim($output)), 2)));
    $summary = implode("\n", $fonts);

    expect($fonts)->not->toBeEmpty();

    foreach ($fonts as $line) {
        $columns = preg_split('/\s+/', trim($line)) ?: [];
        $count = count($columns);

        expect($columns[0])->toMatch('/^[A-Z]{6}\+/', 'Font without subset prefix: '.$summary)
            ->and($columns[$count - 5] ?? '')->toBe('yes', 'Font not embedded: '.$summary)
            ->and($columns[$count - 4] ?? '')->toBe('yes', 'Font not subset: '.$summary);
    }

    expect($summary)->toContain('DejaVuSans')
        ->and($summary)->toContain('DejaVuSans-Bold');
});

it('M09 §7: qpdf checks and linearises the PDF ("fast web view")', function () {
    $pdf = pdfEnvironmentRender($this->pdfFolder);
    $linearized = $this->pdfFolder.'/linearized.pdf';

    pdfEnvironmentRun([config('documents.qpdf_binary'), '--check', $pdf]);
    pdfEnvironmentRun([config('documents.qpdf_binary'), '--linearize', '--object-streams=generate', $pdf, $linearized]);

    expect(pdfEnvironmentRun([config('documents.qpdf_binary'), '--check-linearization', $linearized]))
        ->toContain('no linearization errors');
});
