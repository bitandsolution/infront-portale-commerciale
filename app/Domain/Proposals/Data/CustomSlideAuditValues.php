<?php

namespace App\Domain\Proposals\Data;

use App\Models\ProposalCustomSlide;

/**
 * Fields of a slide composed with blocks in the audit events (M10 §4.1; DM-07): title, layout,
 * section, tick, image of the slide and the blocks as JSON (the whole content, so the history shows
 * what changed).
 */
final class CustomSlideAuditValues
{
    /**
     * @return array<string, mixed>
     */
    public static function of(ProposalCustomSlide $slide): array
    {
        return [
            'custom_slide' => $slide->title,
            'layout' => $slide->layout->value,
            'section' => $slide->section()->value('code'),
            'is_included' => $slide->is_included,
            'image_file_id' => $slide->image_file_id,
            'blocks' => json_encode($slide->blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}
