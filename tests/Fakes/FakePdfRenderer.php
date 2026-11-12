<?php

namespace Tests\Fakes;

use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Data\DocumentPage;
use App\Domain\Documents\Data\PdfMetadata;
use App\Domain\Documents\Data\PdfReport;
use App\Support\Files\SampleFiles;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Renderer of the feature tests (DM-04): no Chromium, no qpdf. It writes a small valid PDF and
 * remembers the pages and metadata it received, so the tests check the composition of the document.
 * The real pipeline is tested by the group "pdf".
 */
final class FakePdfRenderer implements RendersPdf
{
    /** @var list<array{pages: list<DocumentPage>, metadata: PdfMetadata}> */
    public array $rendered = [];

    public ?string $failWith = null;

    public function render(array $pages, PdfMetadata $metadata, string $output): PdfReport
    {
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }

        $this->rendered[] = ['pages' => $pages, 'metadata' => $metadata];
        File::ensureDirectoryExists(dirname($output));
        file_put_contents($output, SampleFiles::pdf());

        return new PdfReport($output, count($pages), (int) filesize($output), ['compose' => 0], [], []);
    }
}
