<?php

namespace App\Http\Controllers\Content;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Content\Actions\SetClubKpis;
use App\Domain\Content\Exceptions\ContentActionNotAllowed;
use App\Domain\Sports\Services\CurrentSeason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ClubKpisRequest;
use App\Models\Club;
use App\Models\ClubKpiValue;
use App\Models\Season;
use App\Models\User;
use App\Support\Content\KpiPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages "Contenuti › KPI e digital" (M08 F-05, F-08, FL-02; D-109, D-491, D-492; F6-01): admin and
 * manager. The Infront clubs of the season of the top bar with their KPI, and the form of a club with
 * the KPI club and digital, source, period and date of the last update. A closed season is read only.
 */
class KpiController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason, KpiPresenter $presenter): Response
    {
        Gate::authorize('viewAny', ClubKpiValue::class);

        $season = $currentSeason->get();

        return Inertia::render('Content/Kpis/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'content_kpi'),
            'page' => $season === null ? null : $presenter->listPage($season),
        ]);
    }

    public function edit(Request $request, Club $club, CurrentSeason $currentSeason, PriceListGrid $grid, KpiPresenter $presenter): Response
    {
        Gate::authorize('viewAny', ClubKpiValue::class);

        $season = $request->filled('stagione')
            ? Season::query()->findOrFail($request->integer('stagione'))
            : $currentSeason->get();

        if ($season === null) {
            throw ContentActionNotAllowed::noKpiSeason();
        }

        if (! $grid->hasClub($season, $club)) {
            throw ContentActionNotAllowed::clubNotInSeason($club->name, $season->code);
        }

        return Inertia::render('Content/Kpis/Edit', [
            'layout' => LayoutProps::for($this->actor($request), 'content_kpi'),
            ...$presenter->clubPage($season, $club),
        ]);
    }

    public function update(ClubKpisRequest $request, Club $club, SetClubKpis $setKpis): RedirectResponse
    {
        Gate::authorize('update', ClubKpiValue::class);

        $season = Season::query()->findOrFail($request->integer('season_id'));
        $changed = $setKpis->handle($season, $club, $request->kpiInputs(), $this->actor($request));

        return back()->with('toast', Toast::success($changed === 0
            ? Text::get('content.toasts.nothing_changed')
            : Text::get('content.toasts.kpis_saved', ['club' => $club->name, 'count' => (string) $changed])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
