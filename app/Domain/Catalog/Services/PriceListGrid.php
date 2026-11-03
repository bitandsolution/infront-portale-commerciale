<?php

namespace App\Domain\Catalog\Services;

use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\LedFormat;
use App\Models\PriceList;
use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rows and columns of the price list grids (D-337 point 1), one implementation for the page, the
 * Actions and the warning at publication.
 *
 * - Rows: Infront clubs, active, enrolled in a competition of the season (in any of its editions),
 *   ordered by name; each with its enrollments of the season for the competition filter.
 * - LED columns: active formats of an active visibility type (in 1.0 the classic one), in their
 *   order. A format deactivated from "Catalogo › Valori" disappears from the grid; its prices stay
 *   in the database untouched.
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

    public function isLedFormat(LedFormat $format): bool
    {
        return $this->ledFormats()->contains('id', $format->id);
    }

    /**
     * Clubs of the grid without any offer (M03 §8, D-333): warning before the publication, never a
     * block. In F2-02 an offer is a LED price of a column of the grid; F2-03 adds the partnership
     * prices.
     *
     * @return Collection<int, Club>
     */
    public function clubsWithoutOffers(PriceList $priceList): Collection
    {
        $formatIds = $this->ledFormats()->modelKeys();

        $offered = $priceList->ledPrices()
            ->whereIn('led_format_id', $formatIds)
            ->distinct()
            ->pluck('club_id')
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
