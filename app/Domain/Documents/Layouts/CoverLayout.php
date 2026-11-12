<?php

namespace App\Domain\Documents\Layouts;

use App\Domain\Documents\Data\LayoutPage;
use App\Enums\DocumentFontWeight;
use App\Enums\DocumentLayout;
use App\Enums\DocumentType;
use Closure;

/**
 * Cover of the documents (layout-dinamici §4.1, D-252; DM-04): the logo of the client in its box and
 * the date of approval of the version at the top right; no company name (the season and "Infront
 * Opportunity" are in the background). The logo keeps its proportions inside the box, centred.
 */
final class CoverLayout
{
    /** The date, right-aligned at x = 1849, top 21: Interstate Bold 17.3 px, white. */
    public const DATE_RIGHT = 1849;

    public const DATE_TOP = 21;

    public const DATE_SIZE = 17.3;

    public const DATE_WIDTH = 400;

    public const DATE_COLOR = '#FFFFFF';

    /**
     * Box of the logo [left, top, width, height] in the network and in the club background.
     *
     * @var array<string, array{int, int, int, int}>
     */
    public const LOGO_BOXES = [
        'network' => [170, 706, 324, 181],
        'club' => [37, 536, 549, 181],
    ];

    public static function page(string $backgroundPath, DocumentType $type, string $date, ?string $logoPath): LayoutPage
    {
        return new LayoutPage(DocumentLayout::Cover, $backgroundPath, [
            'type' => $type,
            'date' => $date,
            'logo' => $logoPath,
        ]);
    }

    /**
     * Boxes, texts and logos for the view, like the recaps: here no boxes, the date and the logo.
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(string): string  $asset
     * @return array{boxes: list<array<string, mixed>>, texts: list<array<string, mixed>>, logos: list<array<string, mixed>>}
     */
    public static function viewData(array $data, Closure $asset): array
    {
        $type = ($data['type'] ?? null) instanceof DocumentType ? $data['type'] : DocumentType::Network;
        $date = is_string($data['date'] ?? null) ? $data['date'] : '';
        $logo = is_string($data['logo'] ?? null) ? $data['logo'] : null;
        $view = ['boxes' => [], 'texts' => [], 'logos' => []];

        if ($date !== '') {
            $view['texts'][] = [
                'left' => self::DATE_RIGHT - self::DATE_WIDTH,
                'top' => self::DATE_TOP,
                'width' => self::DATE_WIDTH,
                'height' => 24,
                'align' => 'right',
                'weight' => DocumentFontWeight::Bold->value,
                'size' => self::DATE_SIZE,
                'color' => self::DATE_COLOR,
                'text' => $date,
                'strike' => false,
            ];
        }

        if ($logo !== null) {
            [$left, $top, $width, $height] = self::LOGO_BOXES[$type->value];
            $view['logos'][] = [
                'src' => $asset($logo),
                'left' => $left,
                'top' => $top,
                'size' => $height,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $view;
    }
}
