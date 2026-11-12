<?php

namespace App\Domain\Content\Data;

use App\Enums\DocumentType;

/**
 * Ordered slides of the document of a group (M09 §6 step 3; D-115, D-118, D-403…D-405), with the
 * warnings for the missing system slides (cover, recap, closing). Null document type without clubs.
 * Every slide whose conditions hold is listed, with its flag "included" for the chosen slides
 * (D-397, D-418): the step "Documento" shows them all, the PDF uses includedSlides().
 */
final readonly class Composition
{
    /**
     * @param  list<int>  $clubIds
     * @param  list<ComposedSlide>  $slides
     * @param  list<string>  $warnings
     */
    public function __construct(
        public ?DocumentType $documentType,
        public array $clubIds,
        public array $slides,
        public array $warnings,
        public DocumentSelection $selection = new DocumentSelection,
    ) {}

    /**
     * The slides that enter the document.
     *
     * @return list<ComposedSlide>
     */
    public function includedSlides(): array
    {
        return array_values(array_filter($this->slides, fn (ComposedSlide $slide): bool => $slide->included));
    }

    /**
     * Pages of the document: those of the included slides.
     */
    public function pageCount(): int
    {
        return array_sum(array_map(fn (ComposedSlide $slide): int => count($slide->instances), $this->includedSlides()));
    }
}
