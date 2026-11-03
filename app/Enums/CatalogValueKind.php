<?php

namespace App\Enums;

use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;

/**
 * Values of the catalogue the admin changes from the page "Catalogo › Valori" (D-331): name, order
 * and, where the table has them, description (LED formats) and active (all but the benefit
 * categories), as in the data model (progettazione-tecnica §5.3.3). Codes, the pools consumed by a
 * format and the free-package flag come only from the system data (D-332).
 *
 * Territories, visibility types and pools are shown read-only: their activation is an evolution
 * (EV-04…EV-06).
 */
enum CatalogValueKind: string
{
    case LedFormat = 'led_format';
    case PartnershipTier = 'partnership_tier';
    case HospitalityVariant = 'hospitality_variant';
    case BenefitCategory = 'benefit_category';

    /**
     * @return class-string<LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::LedFormat => LedFormat::class,
            self::PartnershipTier => PartnershipTier::class,
            self::HospitalityVariant => HospitalityVariant::class,
            self::BenefitCategory => BenefitCategory::class,
        };
    }

    public static function of(LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value): self
    {
        return match (true) {
            $value instanceof LedFormat => self::LedFormat,
            $value instanceof PartnershipTier => self::PartnershipTier,
            $value instanceof HospitalityVariant => self::HospitalityVariant,
            $value instanceof BenefitCategory => self::BenefitCategory,
        };
    }

    public function hasDescription(): bool
    {
        return $this === self::LedFormat;
    }

    public function hasActiveFlag(): bool
    {
        return $this !== self::BenefitCategory;
    }

    public function auditAction(): AuditAction
    {
        return match ($this) {
            self::LedFormat => AuditAction::LedFormatUpdated,
            self::PartnershipTier => AuditAction::PartnershipTierUpdated,
            self::HospitalityVariant => AuditAction::HospitalityVariantUpdated,
            self::BenefitCategory => AuditAction::BenefitCategoryUpdated,
        };
    }

    /**
     * Name of the edit and update routes (catalog.values.<segment>.edit / .update).
     */
    public function routeSegment(): string
    {
        return match ($this) {
            self::LedFormat => 'formats',
            self::PartnershipTier => 'tiers',
            self::HospitalityVariant => 'variants',
            self::BenefitCategory => 'categories',
        };
    }

    /**
     * Italian label of the kind (lang/it/catalog.php), e.g. "Format LED".
     */
    public function label(): string
    {
        $label = __('catalog.value_kinds.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
