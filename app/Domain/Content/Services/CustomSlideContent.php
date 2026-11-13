<?php

namespace App\Domain\Content\Services;

use App\Enums\CustomBlockType;
use App\Enums\CustomSlideLayout;

/**
 * Rules and normalized form of the content of a slide composed with blocks (D-399, D-430; DM-07),
 * the only place that knows them: the request validates with the limits below, the Action stores
 * what normalize() returns, the layout draws only that.
 *
 * - At most 6 blocks; at most 2 images per slide, counting the image of the layout "image_text" and
 *   every block with an image (D-430).
 * - Text with only bold ("**testo**") and bulleted lists (lines starting with "- "), parsed by
 *   CustomText; never HTML, so every datum is escaped in the PDF (D-176).
 * - Table from 1×1 to 6 rows × 4 columns, the first row is the header; shorter rows are padded.
 * - Column "left" / "right" of every block, used by the layout "two_columns" only.
 */
final class CustomSlideContent
{
    public const MAX_BLOCKS = 6;

    public const MAX_IMAGES = 2;

    public const MAX_TABLE_ROWS = 6;

    public const MAX_TABLE_COLUMNS = 4;

    public const MAX_TITLE_LENGTH = 150;

    public const MAX_TEXT_LENGTH = 1000;

    public const MAX_CELL_LENGTH = 80;

    public const MAX_SLIDE_TITLE_LENGTH = 120;

    public const KEY_PATTERN = '/^[A-Za-z0-9_-]{1,32}$/';

    public const LEFT = 'left';

    public const RIGHT = 'right';

    /**
     * Blocks that carry an image, plus the image of the layout "image_text".
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function imageCount(CustomSlideLayout $layout, array $blocks): int
    {
        $count = $layout === CustomSlideLayout::ImageText ? 1 : 0;

        foreach ($blocks as $block) {
            if (CustomBlockType::tryFrom(is_string($block['type'] ?? null) ? $block['type'] : '')?->hasImage() === true) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The normalized blocks of an input already validated by the request: only the fields of each
     * type, texts trimmed with Windows line ends removed, table rows padded to the same width.
     *
     * @param  array<mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $blocks): array
    {
        $normalized = [];

        foreach (array_values($blocks) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = CustomBlockType::tryFrom(is_string($block['type'] ?? null) ? $block['type'] : '');
            $key = is_string($block['key'] ?? null) ? $block['key'] : '';

            if ($type === null || preg_match(self::KEY_PATTERN, $key) !== 1) {
                continue;
            }

            $entry = [
                'key' => $key,
                'type' => $type->value,
                'column' => ($block['column'] ?? null) === self::RIGHT ? self::RIGHT : self::LEFT,
            ];

            if ($type->hasText()) {
                $entry['text'] = self::text($block['text'] ?? '', $type === CustomBlockType::Title);
            }

            if ($type->hasImage()) {
                $fileId = $block['file_id'] ?? null;
                $entry['file_id'] = is_int($fileId) || (is_string($fileId) && ctype_digit($fileId)) ? (int) $fileId : null;
            }

            if ($type === CustomBlockType::Table) {
                $entry['rows'] = self::rows($block['rows'] ?? []);
            }

            $normalized[] = $entry;

            if (count($normalized) === self::MAX_BLOCKS) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * A new empty block of a type (the editor adds it with its own key).
     *
     * @return array<string, mixed>
     */
    public static function emptyBlock(CustomBlockType $type, string $key): array
    {
        return self::normalize([[
            'key' => $key,
            'type' => $type->value,
            'rows' => [['', ''], ['', '']],
        ]])[0];
    }

    private static function text(mixed $value, bool $singleLine): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", is_string($value) ? $value : '');

        if ($singleLine) {
            $text = (string) preg_replace('/\s+/u', ' ', $text);
        }

        $text = trim($text);
        $limit = $singleLine ? self::MAX_TITLE_LENGTH : self::MAX_TEXT_LENGTH;

        return mb_substr($text, 0, $limit);
    }

    /**
     * @return list<list<string>>
     */
    private static function rows(mixed $value): array
    {
        $rows = [];
        $width = 1;

        foreach (array_slice(is_array($value) ? array_values($value) : [], 0, self::MAX_TABLE_ROWS) as $row) {
            $cells = [];

            foreach (array_slice(is_array($row) ? array_values($row) : [], 0, self::MAX_TABLE_COLUMNS) as $cell) {
                $cells[] = mb_substr(trim((string) preg_replace('/\s+/u', ' ', is_scalar($cell) ? (string) $cell : '')), 0, self::MAX_CELL_LENGTH);
            }

            $width = max($width, count($cells));
            $rows[] = $cells;
        }

        if ($rows === []) {
            $rows = [['']];
        }

        return array_map(fn (array $cells): array => array_pad($cells, $width, ''), $rows);
    }
}
