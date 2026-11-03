<?php

namespace App\Domain\Catalog\Services;

use App\Enums\PriceKind;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rows and columns of the price list grids (D-337 point 1, D-341), one implementation for the
 * pages, the Actions and the warning at publication.
 *
 * - Rows: Infront clubs, active, enrolled in any competition of the season (D-341), ordered by
 *   name; each with its enrollments of the season for the competition filter.
 * - Columns: the active values of the catalogue, in their order: LED formats of an active
 *   visibility type (in 1.0 the classic one), partnership tiers without the free package "Libera"
 *   (no price, RB-04), hospitality variants. A value deactivated from "Catalogo › Valori"
 *   disappears from the grid; its prices stay in the database untouched.
 */
final class PriceListGrid
{
    /**
     * @return Collection<int, Club>
     */
    public function clubs(Season $season): Collection
    {
        return $this->clubQuery($season)
            ->with(['enrollments' => fn ($query) => $query
                ->whereHas('competitionSeason', fn (Builder $edition) => $edition->where('season_id', $season->id))
                ->with('competitionSeason.competition')])
            ->orderBy('name')
            ->get();
    }

    public function hasClub(Season $season, Club $club): bool
    {
        return $this->clubQuery($season)->whereKey($club->id)->exists();
    }

    /**
     * @return Collection<int, LedFormat>|Collection<int, PartnershipTier>|Collection<int, HospitalityVariant>
     */
    public function columns(PriceKind $kind): Collection
    {
        return match ($kind) {
            PriceKind::Led => $this->ledFormats(),
            PriceKind::Partnership => $this->partnershipTiers(),
            PriceKind::Hospitality => $this->hospitalityVariants(),
        };
    }

    public function isColumn(PriceKind $kind, LedFormat|PartnershipTier|HospitalityVariant $value): bool
    {
        return $value instanceof ($kind->columnModel()) && $this->columns($kind)->contains('id', $value->id);
    }

    /**
     * @return Collection<int, LedFormat>
     */
    public function ledFormats(): Collection
    {
        return LedFormat::query()
            ->where('is_active', true)
            ->whereHas('visibilityType', fn (Builder $type) => $type->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, PartnershipTier>
     */
    public function partnershipTiers(): Collection
    {
        return PartnershipTier::query()
            ->where('is_active', true)
            ->where('is_free_package', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, HospitalityVariant>
     */
    public function hospitalityVariants(): Collection
    {
        return HospitalityVariant::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Clubs of the grid without any offer (M03 §8, D-333): warning before the publication, never a
     * block. An offer is a LED price or a partnership price of a column of the grid; hospitality
     * alone is not an offer.
     *
     * @return Collection<int, Club>
     */
    public function clubsWithoutOffers(PriceList $priceList): Collection
    {
        $offered = $priceList->ledPrices()
            ->whereIn('led_format_id', $this->ledFormats()->modelKeys())
            ->pluck('club_id')
            ->merge($priceList->partnershipPrices()
                ->whereIn('partnership_tier_id', $this->partnershipTiers()->modelKeys())
                ->pluck('club_id'))
            ->unique()
            ->values()
            ->all();

        return $this->clubQuery($priceList->season)
            ->whereKeyNot($offered)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Builder<Club>
     */
    private function clubQuery(Season $season): Builder
    {
        return Club::query()
            ->where('is_infront', true)
            ->where('is_active', true)
            ->whereIn('id', ClubEnrollment::query()
                ->select('club_id')
                ->whereHas('competitionSeason', fn (Builder $edition) => $edition->where('season_id', $season->id)));
    }
}
