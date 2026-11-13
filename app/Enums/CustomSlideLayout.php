<?php

namespace App\Enums;

use App\Support\Text;

/**
 * Preset layout of a slide composed with blocks (D-399; DM-07), stored in
 * proposal_custom_slides.layout (C8):
 * - one_column: the blocks stacked on the whole width of the content area;
 * - two_columns: two columns of the same width, every block in the left or right one;
 * - image_text: one image of the slide on the left, the blocks stacked on the right.
 */
enum CustomSlideLayout: string
{
    case OneColumn = 'one_column';
    case TwoColumns = 'two_columns';
    case ImageText = 'image_text';

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        return Text::get('content.custom_slides.layouts.'.$this->value);
    }
}
