<?php

namespace App\Domain\Content\Data;

use App\Models\Slide;

/**
 * A slide of the library whose conditions hold for the group (D-115, D-397, D-405): its pages,
 * whether it is mandatory (cover and recap, never excluded), whether it stays in the "summary only"
 * document (cover, recap, closing), whether it enters the document with the chosen slides
 * (DocumentSelection, D-418) and the tiers of its competition condition, which restrict the data of
 * a network slide to the clubs of that competition (D-251).
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
    ) {}
}
