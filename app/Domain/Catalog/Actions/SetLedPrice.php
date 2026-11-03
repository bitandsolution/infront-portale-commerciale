<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Data\PriceChange;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\AuditAction;
use App\Models\Club;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PriceList;
use App\Models\User;
use App\Support\Amounts;
use Illuminate\Support\Facades\DB;

/**
 * One cell of the LED price list (M03 F-05; D-054, D-057, D-190, D-333): the admin writes the cost
 * for 1′ per match of a club and format, or empties the cell ("not available": the row is removed).
 *
 * - The price list is locked for the whole change, so two changes of the same list and the
 *   publication never interleave.
 * - Allowed in draft and after the publication (D-333); refused in a closed season.
 * - Only the clubs and formats of the grid (PriceListGrid, D-337).
 * - One audit event led_price.updated for every price changed, with the value before and after
 *   (null = not available); nothing written when the value is the same. Approved proposals keep
 *   their frozen prices (D-041); drafts are recalculated in F4 (D-045).
 */
final class SetLedPrice
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PriceListGrid $grid,
    ) {}

    /**
     * @param  string|null  $unitPrice  decimal string ("3157.89"), null to make the cell "not available"
     */
    public function handle(PriceList $priceList, Club $club, LedFormat $format, ?string $unitPrice, User $actor): PriceChange
    {
        $unitPrice = $unitPrice === null ? null : Amounts::normalize($unitPrice);

        return DB::transaction(function () use ($priceList, $club, $format, $unitPrice, $actor): PriceChange {
            $priceList = PriceList::query()->with('season')->lockForUpdate()->findOrFail($priceList->id);

            if ($priceList->season->isReadOnly()) {
                throw CatalogActionNotAllowed::seasonClosed($priceList->season->code);
            }

            if (! $this->grid->hasClub($priceList->season, $club)) {
                throw CatalogActionNotAllowed::clubNotInGrid($club->name, $priceList->season->code);
            }

            if (! $this->grid->isLedFormat($format)) {
                throw CatalogActionNotAllowed::formatNotSold($format->name);
            }

            $price = LedPrice::query()
                ->where('price_list_id', $priceList->id)
                ->where('club_id', $club->id)
                ->where('led_format_id', $format->id)
                ->lockForUpdate()
                ->first();

            $before = $price?->unit_price;

            if ($before === $unitPrice) {
                return PriceChange::Unchanged;
            }

            if ($unitPrice === null) {
                $price?->delete();
                $change = PriceChange::Removed;
            } elseif ($price === null) {
                LedPrice::query()->create([
                    'price_list_id' => $priceList->id,
                    'club_id' => $club->id,
                    'led_format_id' => $format->id,
                    'unit_price' => $unitPrice,
                ]);
                $change = PriceChange::Created;
            } else {
                $price->unit_price = $unitPrice;
                $price->save();
                $change = PriceChange::Updated;
            }

            $this->audit->record(
                AuditAction::LedPriceUpdated,
                $actor,
                AuditSubject::priceList($priceList, $club->name.' ('.$club->code.') · '.$format->name.' ('.$format->code.')'),
                ['unit_price' => $before],
                ['unit_price' => $unitPrice],
            );

            return $change;
        });
    }
}
