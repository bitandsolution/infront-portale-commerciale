<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Data\PriceChange;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\PriceKind;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\User;
use App\Support\Amounts;
use Illuminate\Support\Facades\DB;

/**
 * One cell of a price list grid (M03 F-05, F-07, F-09; D-190, D-333): the admin writes the amount of
 * a club and value of the catalogue, or empties the cell ("not available": the row is removed).
 *
 * - LED: cost for 1′ per match of a format (D-054, D-057); partnership: seasonal fixed price of a
 *   tier (D-029), never for the free package "Libera" (RB-04); hospitality: price per seat of a
 *   variant (D-055).
 * - The price list is locked for the whole change, so two changes of the same list and the
 *   publication never interleave.
 * - Allowed in draft and after the publication (D-333); refused in a closed season.
 * - Only the clubs and values of the grid (PriceListGrid, D-337, D-341).
 * - One audit event <kind>_price.updated for every price changed, with the value before and after
 *   (null = not available); nothing written when the value is the same. Approved proposals keep
 *   their frozen prices (D-041); drafts are recalculated in F4 (D-045).
 */
final class SetPrice
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PriceListGrid $grid,
    ) {}

    /**
     * @param  string|null  $amount  decimal string ("3157.89"), null to make the cell "not available"
     */
    public function handle(
        PriceList $priceList,
        PriceKind $kind,
        Club $club,
        LedFormat|PartnershipTier|HospitalityVariant $value,
        ?string $amount,
        User $actor,
    ): PriceChange {
        $amount = $amount === null ? null : Amounts::normalize($amount);

        return DB::transaction(function () use ($priceList, $kind, $club, $value, $amount, $actor): PriceChange {
            $priceList = PriceList::query()->with('season')->lockForUpdate()->findOrFail($priceList->id);

            if ($priceList->season->isReadOnly()) {
                throw CatalogActionNotAllowed::seasonClosed($priceList->season->code);
            }

            if (! $this->grid->hasClub($priceList->season, $club)) {
                throw CatalogActionNotAllowed::clubNotInGrid($club->name, $priceList->season->code);
            }

            if ($value instanceof PartnershipTier && $value->is_free_package) {
                throw CatalogActionNotAllowed::freePackage($value->name);
            }

            if (! $this->grid->isColumn($kind, $value)) {
                throw CatalogActionNotAllowed::valueNotSold($kind, $value->name);
            }

            $model = $kind->priceModel();
            $amountColumn = $kind->amountColumn();

            $price = $model::query()
                ->where('price_list_id', $priceList->id)
                ->where('club_id', $club->id)
                ->where($kind->columnKey(), $value->id)
                ->lockForUpdate()
                ->first();

            /** @var string|null $before */
            $before = $price?->getAttribute($amountColumn);

            if ($before === $amount) {
                return PriceChange::Unchanged;
            }

            if ($amount === null) {
                $price?->delete();
                $change = PriceChange::Removed;
            } elseif ($price === null) {
                $model::query()->create([
                    'price_list_id' => $priceList->id,
                    'club_id' => $club->id,
                    $kind->columnKey() => $value->id,
                    $amountColumn => $amount,
                ]);
                $change = PriceChange::Created;
            } else {
                $price->setAttribute($amountColumn, $amount);
                $price->save();
                $change = PriceChange::Updated;
            }

            $this->audit->record(
                $kind->auditAction(),
                $actor,
                AuditSubject::priceList($priceList, $club->name.' ('.$club->code.') · '.$value->name.' ('.$value->code.')'),
                [$amountColumn => $before],
                [$amountColumn => $amount],
            );

            return $change;
        });
    }
}
