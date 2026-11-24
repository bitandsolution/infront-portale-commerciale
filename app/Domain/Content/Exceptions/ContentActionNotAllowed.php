<?php

namespace App\Domain\Content\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the contents refuses the operation (M08). Nothing has been written. In the portal the
 * user goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class ContentActionNotAllowed extends RuntimeException
{
    /** D-317: the contents of a closed season are read only, like its price list. */
    public static function seasonClosed(string $season): self
    {
        return new self(Text::get('content.errors.season_closed', ['season' => $season]));
    }

    public static function noSeason(): self
    {
        return new self(Text::get('content.errors.no_season'));
    }

    /** D-317: the KPI of a closed season are read only (F6-01). */
    public static function kpisSeasonClosed(string $season): self
    {
        return new self(Text::get('content.errors.kpis_season_closed', ['season' => $season]));
    }

    public static function noKpiSeason(): self
    {
        return new self(Text::get('content.errors.no_kpi_season'));
    }

    /** F6-01: KPI only for the Infront clubs enrolled in a competition of the season. */
    public static function clubNotInSeason(string $club, string $season): self
    {
        return new self(Text::get('content.errors.kpis_club_not_in_season', ['club' => $club, 'season' => $season]));
    }

    /** F6-01: a KPI that is not (or no longer) active cannot be written. */
    public static function kpiNotActive(): self
    {
        return new self(Text::get('content.errors.kpi_not_active'));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
