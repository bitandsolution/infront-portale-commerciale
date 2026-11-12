<?php

namespace App\Domain\Proposals\Data;

use App\Enums\CustomSlideLayout;

/**
 * What the editor of a slide composed with blocks sends (D-399, D-430; DM-07): title, preset layout,
 * section of the document (D-429), image of the layout "image_text" (an image already uploaded for
 * the group, or none) and the blocks normalized by CustomSlideContent.
 */
final readonly class CustomSlideInput
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function __construct(
        public string $title,
        public CustomSlideLayout $layout,
        public string $sectionCode,
        public ?int $imageFileId,
        public array $blocks,
    ) {}
}
