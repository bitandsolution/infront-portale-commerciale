<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\AuditAction;
use App\Enums\PriceKind;
use App\Models\BenefitCategory;
use App\Models\Club;
use App\Models\PartnershipBenefit;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Benefit texts of a club and partnership tier, one per category (M03 F-08, FL-03; D-117, D-335):
 * the admin saves the texts of a tier together; an empty text removes the row.
 *
 * - Optional: no consumer in 1.0 (in the documents the benefits are static slides, D-117); no
 *   warning for tiers without benefits (D-333).
 * - Only the texts of that club and tier change (M03-CA7); no copy between clubs (EV-25).
 * - Not for the free package "Libera" (RB-04), nor for a deactivated tier; refused in a closed season.
 * - One audit event partnership_benefit.updated for every text changed, with the text before and
 *   after (null = no text); nothing written when nothing changes.
 */
final class SetPartnershipBenefits
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PriceListGrid $grid,
    ) {}

    /**
     * @param  array<int, string|null>  $texts  benefit category id → text (null or empty: no text)
     * @return int number of texts changed
     */
    public function handle(PriceList $priceList, Club $club, PartnershipTier $tier, array $texts, User $actor): int
    {
        return DB::transaction(function () use ($priceList, $club, $tier, $texts, $actor): int {
            $priceList = PriceList::query()->with('season')->lockForUpdate()->findOrFail($priceList->id);

            if ($priceList->season->isReadOnly()) {
                throw CatalogActionNotAllowed::seasonClosed($priceList->season->code);
            }

            if (! $this->grid->hasClub($priceList->season, $club)) {
                throw CatalogActionNotAllowed::clubNotInGrid($club->name, $priceList->season->code);
            }

            if ($tier->is_free_package) {
                throw CatalogActionNotAllowed::freePackage($tier->name);
            }

            if (! $this->grid->isColumn(PriceKind::Partnership, $tier)) {
                throw CatalogActionNotAllowed::valueNotSold(PriceKind::Partnership, $tier->name);
            }

            $existing = PartnershipBenefit::query()
                ->where('price_list_id', $priceList->id)
                ->where('club_id', $club->id)
                ->where('partnership_tier_id', $tier->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('benefit_category_id');

            $changed = 0;

            foreach (BenefitCategory::query()->whereKey(array_keys($texts))->orderBy('sort_order')->get() as $category) {
                $text = trim((string) ($texts[$category->id] ?? ''));
                $after = $text === '' ? null : $text;
                $benefit = $existing->get($category->id);
                $before = $benefit?->body;

                if ($before === $after) {
                    continue;
                }

                if ($after === null) {
                    $benefit?->delete();
                } elseif ($benefit === null) {
                    PartnershipBenefit::query()->create([
                        'price_list_id' => $priceList->id,
                        'club_id' => $club->id,
                        'partnership_tier_id' => $tier->id,
                        'benefit_category_id' => $category->id,
                        'body' => $after,
                        'sort_order' => $category->sort_order,
                    ]);
                } else {
                    $benefit->body = $after;
                    $benefit->save();
                }

                $this->audit->record(
                    AuditAction::PartnershipBenefitUpdated,
                    $actor,
                    AuditSubject::priceList($priceList, $club->name.' ('.$club->code.') · '.$tier->name.' ('.$tier->code.') · '.$category->name),
                    ['body' => $before],
                    ['body' => $after],
                );

                $changed++;
            }

            return $changed;
        });
    }
}
