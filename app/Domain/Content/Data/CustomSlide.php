<?php

namespace App\Domain\Content\Data;

use App\Enums\CustomBlockType;
use App\Enums\CustomSlideLayout;
use App\Models\ProposalCustomSlide;
use App\Models\SlideSection;

/**
 * A slide composed with blocks (D-399, D-428…D-432; DM-07) as the composition and the document read
 * it: from the working row of the group, or from the copy frozen on the versions at the approval
 * (proposal_versions.custom_slides), so a regeneration draws the same content.
 *
 * The blocks are those normalized by CustomSlideContent: key, type, column ("left" / "right") and
 * text, rows (table) or file_id (images).
 */
final readonly class CustomSlide
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function __construct(
        public int $id,
        public string $title,
        public CustomSlideLayout $layout,
        public string $sectionCode,
        public ?int $imageFileId,
        public array $blocks,
        public bool $included = true,
        public int $sortOrder = 1,
    ) {}

    public static function fromModel(ProposalCustomSlide $slide): self
    {
        return new self(
            id: $slide->id,
            title: $slide->title,
            layout: $slide->layout,
            sectionCode: $slide->section->code,
            imageFileId: $slide->layout === CustomSlideLayout::ImageText ? $slide->image_file_id : null,
            blocks: $slide->blocks,
            included: $slide->is_included,
            sortOrder: $slide->sort_order,
        );
    }

    /**
     * From the copy frozen on a version; null for a malformed entry.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $layout = CustomSlideLayout::tryFrom(is_string($data['layout'] ?? null) ? $data['layout'] : '');

        if ($layout === null || ! is_int($data['id'] ?? null)) {
            return null;
        }

        $blocks = [];

        foreach (is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $block) {
            if (is_array($block) && CustomBlockType::tryFrom(is_string($block['type'] ?? null) ? $block['type'] : '') !== null) {
                /** @var array<string, mixed> $block */
                $blocks[] = $block;
            }
        }

        return new self(
            id: $data['id'],
            title: is_string($data['title'] ?? null) ? $data['title'] : '',
            layout: $layout,
            sectionCode: is_string($data['section'] ?? null) ? $data['section'] : SlideSection::BRAND_EXPOSURE,
            imageFileId: is_int($data['image_file_id'] ?? null) ? $data['image_file_id'] : null,
            blocks: $blocks,
            included: (bool) ($data['included'] ?? true),
            sortOrder: is_int($data['sort_order'] ?? null) ? $data['sort_order'] : 1,
        );
    }

    /**
     * The slides frozen on a version.
     *
     * @return list<self>
     */
    public static function listFromArray(mixed $data): array
    {
        $slides = [];

        foreach (is_array($data) ? $data : [] as $entry) {
            $slide = is_array($entry) ? self::fromArray($entry) : null;

            if ($slide !== null) {
                $slides[] = $slide;
            }
        }

        return $slides;
    }

    /**
     * The copy frozen on the versions.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sortOrder,
            'title' => $this->title,
            'layout' => $this->layout->value,
            'section' => $this->sectionCode,
            'image_file_id' => $this->imageFileId,
            'blocks' => $this->blocks,
            'included' => $this->included,
        ];
    }

    /**
     * The images of the slide: that of the layout "image_text" and those of the blocks.
     *
     * @return list<int>
     */
    public function imageFileIds(): array
    {
        $ids = $this->imageFileId !== null ? [$this->imageFileId] : [];

        foreach ($this->blocks as $block) {
            if (is_int($block['file_id'] ?? null)) {
                $ids[] = $block['file_id'];
            }
        }

        return array_values(array_unique($ids));
    }
}
