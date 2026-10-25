<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\CreateSeason;
use App\Domain\Sports\Actions\UpdateSeason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\SeasonRequest;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Sports\SportsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seasons page (M02 F-03; D-137: admin only): list, new season, change of name, dates and status
 * (D-317), competitions of the season with their standard number of home matches (D-034).
 * Every write goes through an Action (CLAUDE.md §4).
 */
class SeasonController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Season::class);

        $seasons = Season::query()
            ->withCount('competitionSeasons')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (Season $season): array => SportsPresenter::seasonRow($season, (int) $season->getAttribute('competition_seasons_count')))
            ->values()
            ->all();

        return Inertia::render('Sports/Seasons/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_seasons'),
            'seasons' => $seasons,
            'createUrl' => route('sports.seasons.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Season::class);

        return Inertia::render('Sports/Seasons/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_seasons'),
            'season' => null,
            'statuses' => SportsPresenter::seasonStatusOptions(),
            'saveUrl' => route('sports.seasons.store'),
            'indexUrl' => route('pages.sports_seasons'),
            'editions' => [],
            'availableCompetitions' => [],
            'addEditionUrl' => null,
        ]);
    }

    public function store(SeasonRequest $request, CreateSeason $createSeason): RedirectResponse
    {
        $season = $createSeason->handle($request->seasonData(), $this->actor($request));

        return redirect()->route('sports.seasons.edit', $season)->with('toast', Toast::success(
            Text::get('sports.toasts.season_created', ['season' => $season->code]),
        ));
    }

    public function edit(Request $request, Season $season): Response
    {
        Gate::authorize('update', $season);

        $editions = CompetitionSeason::query()
            ->where('season_id', $season->id)
            ->with(['competition', 'season'])
            ->get()
            ->sortBy(fn (CompetitionSeason $edition): array => [$edition->competition->sort_order, $edition->competition->name])
            ->values();

        $available = Competition::query()
            ->where('is_active', true)
            ->whereNotIn('id', $editions->pluck('competition_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Competition $competition): array => ['value' => (string) $competition->id, 'label' => $competition->name])
            ->values()
            ->all();

        return Inertia::render('Sports/Seasons/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_seasons'),
            'season' => [
                'id' => $season->id,
                'code' => $season->code,
                'name' => $season->name,
                'startsOn' => $season->starts_on->toDateString(),
                'endsOn' => $season->ends_on->toDateString(),
                'status' => $season->status->value,
                'readOnly' => $season->isReadOnly(),
            ],
            'statuses' => SportsPresenter::seasonStatusOptions(),
            'saveUrl' => route('sports.seasons.update', $season),
            'indexUrl' => route('pages.sports_seasons'),
            'editions' => $editions->map(fn (CompetitionSeason $edition): array => SportsPresenter::editionRow($edition))->all(),
            'availableCompetitions' => $available,
            'addEditionUrl' => route('sports.editions.store', $season),
        ]);
    }

    public function update(SeasonRequest $request, Season $season, UpdateSeason $updateSeason): RedirectResponse
    {
        $changed = $updateSeason->handle($season, $request->seasonData(), $this->actor($request));

        return back()->with('toast', $changed
            ? Toast::success(Text::get('sports.toasts.season_updated', ['season' => $season->code]))
            : Toast::success(Text::get('sports.toasts.nothing_changed')));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
