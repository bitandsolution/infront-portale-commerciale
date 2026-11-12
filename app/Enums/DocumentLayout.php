<?php

namespace App\Enums;

use LogicException;

/**
 * Dynamic layouts of the slides (M08 §4.3, D-116, layout_code of slides). Position and style of the
 * data are defined in code (docs/layout-dinamici.md). Slice S1 built the network recap (D-309).
 *
 * DM-02 adds the codes of the system slides of the library (D-405), so admin and manager can upload
 * their backgrounds: cover and club recap are drawn by DM-04 (CoverLayout, NetworkRecapLayout with
 * the club RecapStyle), the LED simulation (a slide without image, one page per club with the LED)
 * by DM-06. Slice F7 adds the other KPI layouts.
 */
enum DocumentLayout: string
{
    case Cover = 'cover';
    case NetworkRecap = 'network_recap';
    case ClubRecap = 'club_recap';
    case LedMockup = 'led_mockup';

    /**
     * Blade view of the layout (resources/views/documents/layouts).
     */
    public function view(): string
    {
        return match ($this) {
            self::Cover, self::NetworkRecap, self::ClubRecap => 'documents.layouts.positioned',
            self::LedMockup => throw self::notBuilt($this),
        };
    }

    /** The recap of the proposal: mandatory and repeated for every variant (D-396, D-397). */
    public function isRecap(): bool
    {
        return $this === self::NetworkRecap || $this === self::ClubRecap;
    }

    /** Slides that cannot be excluded from the document (D-397): cover and recap. */
    public function isMandatory(): bool
    {
        return $this === self::Cover || $this->isRecap();
    }

    /** The LED simulation has no image of its own: the photo of the field comes from DM-06 (D-405). */
    public function needsImage(): bool
    {
        return $this !== self::LedMockup;
    }

    /**
     * The only document type the layout belongs to (recaps), null when it fits both.
     */
    public function documentType(): ?DocumentType
    {
        return match ($this) {
            self::NetworkRecap => DocumentType::Network,
            self::ClubRecap => DocumentType::Club,
            self::Cover, self::LedMockup => null,
        };
    }

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.layouts.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }

    public static function notBuilt(self $layout): LogicException
    {
        return new LogicException("The layout {$layout->value} is not built yet (DM-04, DM-06).");
    }
}
