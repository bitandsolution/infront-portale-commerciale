<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Data\CatalogAuditValues;
use App\Domain\Catalog\Data\CatalogValueData;
use App\Enums\CatalogValueKind;
use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change of a value of the catalogue from the page "Catalogo › Valori" (D-331): admin only.
 *
 * Only name, order and, where the table has them, description and active: the code, the pools
 * consumed by a format and the free-package flag of the "Libera" come only from the system data
 * (D-332) and are never touched here. A deactivated value disappears from the next pages of the
 * slice (price lists, configurator); nothing is ever deleted (C4). One audit event in the area
 * "catalog" with only the changed fields; nothing written when nothing changes.
 */
final class UpdateCatalogValue
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value, CatalogValueData $data, User $actor): bool
    {
        $kind = CatalogValueKind::of($value);

        return DB::transaction(function () use ($value, $kind, $data, $actor): bool {
            $value = $value->newQuery()->whereKey($value->id)->lockForUpdate()->firstOrFail();
            $before = CatalogAuditValues::value($value);

            $value->name = $data->name;
            $value->sort_order = $data->sortOrder;

            if ($value instanceof LedFormat) {
                $value->description = $data->description;
            }

            if (! $value instanceof BenefitCategory && $data->isActive !== null) {
                $value->is_active = $data->isActive;
            }

            [$old, $new] = AuditLog::changes($before, CatalogAuditValues::value($value));

            if ($new === []) {
                return false;
            }

            $value->save();

            $this->audit->record($kind->auditAction(), $actor, AuditSubject::catalogValue($value), $old, $new);

            return true;
        });
    }
}
