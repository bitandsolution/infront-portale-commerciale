<?php

namespace App\Domain\Content\Data;

use App\Models\Club;
use App\Models\Slide;
use App\Models\SlideSection;

/**
 * Fields of a slide in the audit events (D-284, D-406): readable codes instead of ids where they
 * exist. AuditLog drops the fields called "code", so the codes are written as club_code and
 * section_code. The conditions are one string, e.g. "partnership_tier: PREMIUM, TOP; has_led: true",
 * null without conditions.
 */
final class ContentAuditValues
{
    /**
     * @param  array<string, list<string>>  $conditions
     * @return array<string, scalar|null>
     */
    public static function slide(Slide $slide, array $conditions): array
    {
        return [
            'title' => $slide->title,
            'scope' => $slide->scope->value,
            'club_code' => $slide->club_id !== null ? Club::query()->whereKey($slide->club_id)->value('code') : null,
            'document_type' => $slide->document_type->value,
            'section_code' => SlideSection::query()->whereKey($slide->slide_section_id)->value('code'),
            'sort_order' => $slide->sort_order,
            'kind' => $slide->kind->value,
            'layout_code' => $slide->layout_code?->value,
            'image_file_id' => $slide->image_file_id,
            'is_active' => $slide->is_active,
            'conditions' => self::conditions($conditions),
        ];
    }

    /**
     * @param  array<string, list<string>>  $conditions
     */
    public static function conditions(array $conditions): ?string
    {
        $parts = [];

        foreach ($conditions as $type => $values) {
            if ($values !== []) {
                $parts[] = $type.': '.implode(', ', $values);
            }
        }

        return $parts === [] ? null : implode('; ', $parts);
    }
}
