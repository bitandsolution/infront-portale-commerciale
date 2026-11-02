<?php

namespace App\Domain\Catalog\Data;

use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;

/**
 * Fields of the catalogue recorded in the audit events (D-127, D-284): plain values, only the
 * fields the admin can change (AuditLog::changes() keeps only the changed ones). The code is in the
 * label of the subject (AuditSubject::catalogValue): AuditLog never records a field named "code".
 */
final class CatalogAuditValues
{
    /**
     * @return array<string, string|int|bool|null>
     */
    public static function value(LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value): array
    {
        $fields = ['name' => $value->name];

        if ($value instanceof LedFormat) {
            $fields['description'] = $value->description;
        }

        if (! $value instanceof BenefitCategory) {
            $fields['is_active'] = $value->is_active;
        }

        $fields['sort_order'] = $value->sort_order;

        return $fields;
    }
}
