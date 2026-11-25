<?php

namespace App\Domain\Content\Data;

use App\Enums\DocumentType;

/**
 * Ordered slides of the document of a group (M09 §6 step 3; D-115, D-118, D-403…D-405), with the
 * warnings for the missing system slides (cover, recap, closing). Null document type without clubs.
 * Every slide whose conditions hold is listed, with its flag "included" for the chosen slides
 * (D-397, D-418): the step "Documento" shows them all, the PDF uses includedSlides(). The candidates
 * left out by their conditions are in $excluded, for the simulator (D-494, D-495).
 *
 * KPI (F6-03; D-500…D-503): $kpis are the KPI in force of the clubs of the group, for the snapshot of
 * the document; $missingKpis the clubs without some of the KPI the document type uses, kept apart from
 * $warnings so that the note of the agent about the missing system slides does not fire for them.
 */
final readonly class Composition
{
    /**
     * @param  list<int>  $clubIds
     * @param  list<ComposedSlide>  $slides
     * @param  list<string>  $warnings
     * @param  list<ExcludedSlide>  $excluded
     * @param  list<ClubMissingKpis>  $missingKpis
     */
    public function __construct(
        public ?DocumentType $documentType,
        public array $clubIds,
        public array $slides,
        public array $warnings,
        public DocumentSelection $selection = new DocumentSelection,
        public array $excluded = [],
        public ?ClubKpiSet $kpis = null,
        public array $missingKpis = [],
    ) {}

    /**
     * The warnings "club senza KPI" (D-502, D-503), for admin and manager.
     *
     * @return list<string>
     */
    public function kpiWarnings(): array
    {
        return array_map(fn (ClubMissingKpis $missing): string => $missing->warning(), $this->missingKpis);
    }

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
     * Pages of the document: the drawable pages of the included slides (D-427, D-499).
     */
    public function pageCount(): int
    {
        return array_sum(array_map(fn (ComposedSlide $slide): int => $slide->pageCount(), $this->includedSlides()));
    }
}
