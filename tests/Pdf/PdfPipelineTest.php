<?php

use App\Domain\Documents\Data\PdfMetadata;
use App\Domain\Documents\Data\StaticSlidePage;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Domain\Documents\Services\Chromium;
use App\Domain\Documents\Services\PdfPipeline;
use App\Domain\Documents\Services\PdfTools;
use App\Support\Documents\SampleDeck;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/*
| Proof of the PDF pipeline (fetta S1, D-309…D-312): a static slide and the network recap, the deck of
| 40 slides of M09-CA4, the isolation of Chromium from the network. DejaVu substitute font (D-311).
*/

beforeEach(function () {
    $this->pipelineFolder = storage_path('framework/testing/pdf-pipeline');
    File::ensureDirectoryExists($this->pipelineFolder);
});

afterEach(function () {
    File::deleteDirectory($this->pipelineFolder);
});

it('S1 (D-309): a static slide and the network recap become one optimised PDF', function () {
    $folder = $this->pipelineFolder;
    file_put_contents($folder.'/statica.png', SampleDeck::staticSlide(1));
    file_put_contents($folder.'/sfondo.png', SampleDeck::background('Proposta economica'));

    $pages = [
        new StaticSlidePage($folder.'/statica.png'),
        ...NetworkRecapLayout::pages(SampleDeck::recap(3, SampleDeck::DISCOUNT_PERCENT, false, $folder), $folder.'/sfondo.png'),
    ];

    $report = app(PdfPipeline::class)->render($pages, new PdfMetadata('Infront Proposta – Network', 'Stagione 2027-28'), $folder.'/documento.pdf');
    $tools = app(PdfTools::class);
    $info = $tools->info($report->path);
    $images = (new Process(['pdfimages', '-list', $report->path]))->mustRun()->getOutput();

    expect($report->pages)->toBe(2)
        ->and($report->bytes)->toBe(filesize($report->path))
        ->and(array_keys($report->timings))->toBe(['compose', 'render', 'optimize', 'check'])
        ->and($report->warnings)->toBe([])
        ->and($tools->isLinearized($report->path))->toBeTrue()
        ->and($info['Title'])->toBe('Infront Proposta – Network')
        ->and($info['Author'])->toBe('Infront Italy')
        ->and($info['Subject'])->toBe('Stagione 2027-28')
        ->and($info['Page size'])->toStartWith('1440 x 810 pts')
        ->and(array_map(fn ($font): string => preg_replace('/^[A-Z]{6}\+/', '', $font->name) ?? '', $report->fonts))
        ->toContain('DejaVuSans-Bold')
        // D-310: the PNG slides arrive in the PDF as JPEG (DCT), the two backgrounds included.
        ->and(substr_count($images, ' jpeg '))->toBeGreaterThanOrEqual(2);

    // The folder of the job is removed at the end (D-312).
    expect(glob(storage_path('framework/documents/*'), GLOB_ONLYDIR) ?: [])->toBe([]);
});

it('M09-CA4: a deck of 40 slides weighs at most 15 MB and opens progressively (linearised)', function () {
    $deck = SampleDeck::deck($this->pipelineFolder.'/sorgenti', 40);
    $report = app(PdfPipeline::class)->render($deck, new PdfMetadata('Infront Proposta – Network', 'Stagione 2027-28'), $this->pipelineFolder.'/deck.pdf');

    fwrite(STDOUT, sprintf(
        "\n      Deck di 40 slide: %.1f MB, %d ms (composizione %d, Chromium %d, qpdf %d, controlli %d)\n",
        $report->bytes / 1048576,
        $report->totalMilliseconds(),
        $report->timings['compose'],
        $report->timings['render'],
        $report->timings['optimize'],
        $report->timings['check'],
    ));

    expect($report->pages)->toBe(40)
        ->and($report->bytes)->toBeLessThanOrEqual(15 * 1024 * 1024)
        ->and(app(PdfTools::class)->isLinearized($report->path))->toBeTrue()
        // progettazione-tecnica §5.5.2: document ready within a minute of the approval.
        ->and($report->totalMilliseconds())->toBeLessThan(60_000);
});

it('D-312: Chromium never reaches the network, loopback included', function () {
    $folder = $this->pipelineFolder;
    $log = $folder.'/requests.log';
    $port = pdfPipelineFreePort();
    file_put_contents($folder.'/router.php', '<?php file_put_contents('.var_export($log, true).', $_SERVER["REQUEST_URI"]."\n", FILE_APPEND); http_response_code(200);');

    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, $folder.'/router.php']);
    $server->start();

    try {
        // The server answers: a request from PHP is logged.
        $deadline = microtime(true) + 5;
        while (@file_get_contents("http://127.0.0.1:{$port}/probe") === false && microtime(true) < $deadline) {
            usleep(100_000);
        }
        expect(file_get_contents($log))->toBe("/probe\n");
        unlink($log);

        file_put_contents($folder.'/pagina.html', <<<HTML
            <!doctype html>
            <html><head>
            <link rel="stylesheet" href="http://127.0.0.1:{$port}/foglio.css">
            <style>@import url("http://localhost:{$port}/import.css"); body { background: url("https://127.0.0.1:{$port}/sfondo.png"); }</style>
            <script src="http://127.0.0.1:{$port}/script.js"></script>
            </head><body>
            <img src="http://127.0.0.1:{$port}/immagine.png">
            <iframe src="http://localhost:{$port}/frame"></iframe>
            <p>Pagina con risorse remote</p>
            </body></html>
            HTML);

        app(Chromium::class)->browsershot($folder.'/pagina.html')->savePdf($folder.'/pagina.pdf');

        expect(is_file($folder.'/pagina.pdf'))->toBeTrue()
            ->and(is_file($log) ? file_get_contents($log) : '')->toBe('');
    } finally {
        $server->stop();
    }
});

/**
 * A TCP port free on the loopback interface.
 */
function pdfPipelineFreePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $name = $socket === false ? '' : (string) stream_socket_get_name($socket, false);

    if ($socket !== false) {
        fclose($socket);
    }

    return (int) substr($name, strrpos($name, ':') + 1);
}
