<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\AuditAction;
use App\Enums\PriceListStatus;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Publication of the price list of a season (M03 F-11, FL-01 step 3; D-333): admin only, after an
 * explicit confirmation in the page (ux-architettura §4.5, "Pubblica listino").
 *
 * - Final: a published price list never goes back to draft (D-333), so a second publication is
 *   refused. From now on the configurator can use it (RB-09, F4).
 * - The clubs of the grid without any offer are a warning shown before the confirmation, never a
 *   block (M03 §8); their number is recorded in the event. No warning for tiers without benefits:
 *   benefits are optional (D-117, D-333).
 * - Refused in a closed season; audit price_list.published in the same transaction.
 */
final class PublishPriceList
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PriceListGrid $grid,
    ) {}

    public function handle(PriceList $priceList, User $actor): PriceList
    {
        return DB::transaction(function () use ($priceList, $actor): PriceList {
            $priceList = PriceList::query()->with('season')->lockForUpdate()->findOrFail($priceList->id);

            if ($priceList->season->isReadOnly()) {
                throw CatalogActionNotAllowed::seasonClosed($priceList->season->code);
            }

            if ($priceList->isPublished()) {
                throw CatalogActionNotAllowed::alreadyPublished($priceList->season->code);
            }

            $withoutOffers = $this->grid->clubsWithoutOffers($priceList)->count();

            $priceList->status = PriceListStatus::Published;
            $priceList->published_at = Carbon::now();
            $priceList->published_by = $actor->id;
            $priceList->save();

            $this->audit->record(
                AuditAction::PriceListPublished,
                $actor,
                AuditSubject::priceList($priceList),
                ['status' => PriceListStatus::Draft->value],
                ['status' => PriceListStatus::Published->value, 'clubs_without_offers' => $withoutOffers],
            );

            return $priceList;
        });
    }
}
