<?php

namespace App\Domain\Documents\Data;

use App\Enums\DocumentLayout;

/**
 * Dynamic slide: background image plus the data of a layout (D-116), rendered by the layout's Blade
 * view with vector text. One layout can need more pages (continuation of the tables): each page is
 * a LayoutPage with its share of the data. The LED simulation has no background image: its photo is
 * part of the layout (DM-06).
 */
final readonly class LayoutPage implements DocumentPage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public DocumentLayout $layout,
        public ?string $backgroundPath,
        public array $data,
    ) {}
}
