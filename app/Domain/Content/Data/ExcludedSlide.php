<?php

namespace App\Domain\Content\Data;

use App\Models\Slide;

/**
 * A candidate slide of the document left out by its conditions (M08 F-04; D-494, D-495): the
 * condition types no club of any variant satisfies, with the values the slide asks for (type → values),
 * or the LED simulation of a group without any LED ($withoutLed, D-405). Only the simulator shows them.
 */
final readonly class ExcludedSlide
{
    /**
     * @param  array<string, list<string>>  $unmetConditions
     */
    public function __construct(
        public Slide $slide,
        public array $unmetConditions,
        public bool $withoutLed = false,
    ) {}
}
