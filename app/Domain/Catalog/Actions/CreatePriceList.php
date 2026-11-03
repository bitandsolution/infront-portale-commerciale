<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\PriceListStatus;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Price list of a season, created empty in draft by the admin (M03 F-11; D-190, D-333).
 *
 * - One per season: a second one is refused (the UNIQUE key is the last guard).
 * - Refused in a closed season (D-333, as D-317).
 * - The copy from the previous season with +% is the 1.1 release (M03 F-12, D-236).
 * - Audit price_list.created in the same transaction.
 */
final class CreatePriceList
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Season $season, User $actor): PriceList
    {
        return DB::transaction(function () use ($season, $actor): PriceList {
            $season = Season::query()->lockForUpdate()->findOrFail($season->id);

            if ($season->isReadOnly()) {
                throw CatalogActionNotAllowed::seasonClosed($season->code);
            }

            if (PriceList::query()->where('season_id', $season->id)->exists()) {
                throw CatalogActionNotAllowed::priceListExists($season->code);
            }

            $priceList = PriceList::query()->create([
                'season_id' => $season->id,
                'status' => PriceListStatus::Draft,
            ]);
            $priceList->setRelation('season', $season);

            $this->audit->record(
                AuditAction::PriceListCreated,
                $actor,
                AuditSubject::priceList($priceList),
                [],
                ['status' => PriceListStatus::Draft->value],
            );

            return $priceList;
        });
    }
}
