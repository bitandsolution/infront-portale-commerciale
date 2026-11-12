<?php

namespace App\Domain\Documents\Data;

use App\Enums\DocumentType;
use App\Models\Proposal;
use App\Models\ProposalVersion;

/**
 * A document of a group composed and ready to render (DocumentBuilder, DM-04): the pages, the PDF
 * metadata and what is saved on the row of the document (D-111, D-204, D-413).
 */
final readonly class BuiltDocument
{
    /**
     * @param  list<DocumentPage>  $pages
     * @param  array<string, mixed>  $pageModel
     * @param  array<string, mixed>  $kpiSnapshot
     */
    public function __construct(
        public DocumentType $type,
        public Proposal $firstProposal,
        public ProposalVersion $firstVersion,
        public array $pages,
        public PdfMetadata $metadata,
        public string $fileName,
        public array $pageModel,
        public array $kpiSnapshot,
    ) {}
}
