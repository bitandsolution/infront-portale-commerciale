<?php

namespace App\Enums;

/**
 * Inclusion conditions of a slide (M08 §4.2; D-115, D-202): one row per value with a stable code.
 * Rows of the same type are alternatives (OR), different types must all be true (AND); no rows =
 * always included (RB-01). A condition is true when at least one club of at least one variant of the
 * group satisfies it (D-118, D-404).
 *
 * Values: partnership_tier and led_format = codes of the catalogue (TOP, JUMBO…); has_* = "true" or
 * "false" (Sì / No); competition_tier = tier of the competition ("1" Serie A, "2" Serie B, "3"
 * Serie C).
 */
enum SlideConditionType: string
{
    case PartnershipTier = 'partnership_tier';
    case HasPartnership = 'has_partnership';
    case LedFormat = 'led_format';
    case HasLed = 'has_led';
    case HasHospitality = 'has_hospitality';
    case CompetitionTier = 'competition_tier';

    public const TRUE = 'true';

    public const FALSE = 'false';

    /** Yes / no conditions: the value is "true" or "false", one of the two. */
    public function isBoolean(): bool
    {
        return in_array($this, [self::HasPartnership, self::HasLed, self::HasHospitality], true);
    }

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.conditions.types.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
