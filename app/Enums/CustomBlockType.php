<?php

namespace App\Enums;

use App\Support\Text;

/**
 * Kind of a block of a slide composed with blocks (D-399, D-430; DM-07): a title, a text with bold
 * and bulleted lists, a simple table with the first row as header, an image, an image with a text
 * at its right.
 */
enum CustomBlockType: string
{
    case Title = 'title';
    case Text = 'text';
    case Table = 'table';
    case Image = 'image';
    case ImageText = 'image_text';

    /** Blocks that carry an image (D-430: at most 2 images per slide). */
    public function hasImage(): bool
    {
        return $this === self::Image || $this === self::ImageText;
    }

    /** Blocks with a text written by the user. */
    public function hasText(): bool
    {
        return $this === self::Title || $this === self::Text || $this === self::ImageText;
    }

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        return Text::get('content.custom_slides.blocks.'.$this->value);
    }
}
