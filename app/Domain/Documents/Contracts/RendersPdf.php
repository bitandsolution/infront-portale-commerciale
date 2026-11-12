<?php

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\Data\DocumentPage;
use App\Domain\Documents\Data\PdfMetadata;
use App\Domain\Documents\Data\PdfReport;

/**
 * Renders composed pages into a checked PDF file (PdfPipeline). The generation of the documents
 * (DM-04) depends on this contract, so the feature tests replace Chromium and qpdf with a fake
 * renderer; the pipeline itself is tested by the group "pdf" (D-308).
 */
interface RendersPdf
{
    /**
     * @param  list<DocumentPage>  $pages
     */
    public function render(array $pages, PdfMetadata $metadata, string $output): PdfReport;
}
