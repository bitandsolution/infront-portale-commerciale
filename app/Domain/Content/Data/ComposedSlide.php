<?php

namespace App\Domain\Content\Data;

use App\Models\Slide;
use App\Models\SlideSection;

/**
 * A slide of the library whose conditions hold for the group (D-115, D-397, D-405): its pages,
 * whether it is mandatory (cover and recap, never excluded), whether it stays in the "summary only"
 * document (cover, recap, closing), whether it enters the document with the chosen slides
 * (DocumentSelection, D-418) and the tiers of its competition condition, which restrict the data of
 * a network slide to the clubs of that competition (D-251).
 *
 * A slide composed with blocks by the agent (D-399, DM-07) is listed like the others: its slide is
 * the background "Slide personalizzata" of the library (D-428), $custom its content and
 * $customSection the section it closes (D-429).
 */
final readonly class ComposedSlide
{
    /**
     * @param  list<SlideInstance>  $instances
     * @param  list<int>  $competitionTiers
     */
    public function __construct(
        public Slide $slide,
        public bool $mandatory,
        public bool $inSummary,
        public array $instances,
        public array $competitionTiers,
        public bool $included = true,
        public ?CustomSlide $custom = null,
        public ?SlideSection $customSection = null,
    ) {}

    /** The section the slide belongs to in the document. */
    public function section(): SlideSection
    {
        return $this->customSection ?? $this->slide->section;
    }

    /** Title shown in the step "Documento" and in the page model. */
    public function title(): string
    {
        return $this->custom !== null ? $this->custom->title : $this->slide->title;
    }
}
