<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\ChangeMatchStatus;
use App\Domain\Sports\Actions\SaveMatch;
use App\Domain\Sports\Data\SavedMatch;
use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\MatchdayStage;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\MatchRequest;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Navigation\PortalPages;
use App\Support\Sports\SportsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Matches page (M02 F-08, FL-03; D-137: admin and manager): matches of one competition of the season
 * of the top bar (D-317), filtered by club and matchday; manual entry and change, cancellation and
 * restore (D-319). A closed season is read-only. The rules are in SaveMatch and ChangeMatchStatus.
 */
class MatchController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason): Response
    {
        Gate::authorize('viewAny', FixtureMatch::class);

        $season = $currentSeason->get();
        $edition = $season !== null ? $this->edition($season, $request->integer('edizione')) : null;
        $clubId = $request->integer('club');
        $matchday = $request->integer('giornata');
        $matches = [];

        if ($edition !== null) {
            $matches = FixtureMatch::query()
                ->join('matchdays', 'matchdays.id', '=', 'matches.matchday_id')
                ->where('matchdays.competition_season_id', $edition->id)
                ->when($clubId > 0, fn ($query) => $query->where(fn ($inner) => $inner->where('home_club_id', $clubId)->orWhere('away_club_id', $clubId)))
                ->when($matchday > 0, fn ($query) => $query->where('matchdays.number', $matchday))
                ->orderByRaw("FIELD(matchdays.stage, 'regular', 'playoff', 'playout')")
                ->orderBy('matchdays.number')
                ->orderBy('matches.match_date')
                ->orderBy('matches.kickoff_time')
                ->select('matches.*')
                ->with(['matchday', 'homeClub', 'awayClub', 'stadium'])
                ->get()
                ->map(fn (FixtureMatch $match): array => SportsPresenter::matchRow($match))
                ->values()
                ->all();
        }

        return Inertia::render('Sports/Matches/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_matches'),
            'season' => $season !== null ? ['code' => $season->code, 'readOnly' => $season->isReadOnly()] : null,
            'editions' => $season !== null ? SportsPresenter::editionOptions($season) : [],
            'filters' => [
                'edition' => $edition !== null ? (string) $edition->id : '',
                'club' => $clubId > 0 ? (string) $clubId : '',
                'matchday' => $matchday > 0 ? (string) $matchday : '',
            ],
            'clubs' => $edition !== null ? $this->clubOptions($edition) : [],
            'matches' => $matches,
            'indexUrl' => PortalPages::url('sports_matches'),
            'createUrl' => $edition !== null ? route('sports.matches.create', ['edizione' => $edition->id]) : null,
        ]);
    }

    public function create(Request $request, CurrentSeason $currentSeason): Response|RedirectResponse
    {
        Gate::authorize('create', FixtureMatch::class);

        $season = $currentSeason->get();
        $edition = $season !== null ? $this->edition($season, $request->integer('edizione')) : null;

        if ($season === null || $edition === null) {
            return redirect(PortalPages::url('sports_matches'));
        }

        return $this->form($request, $season, null, $edition);
    }

    public function store(MatchRequest $request, SaveMatch $saveMatch): RedirectResponse
    {
        $saved = $saveMatch->handle(null, $request->matchData(), $this->actor($request));

        return redirect(PortalPages::url('sports_matches').'?edizione='.$saved->match->matchday->competition_season_id)
            ->with('toast', $this->toast($saved, 'sports.toasts.match_created'));
    }

    public function edit(Request $request, FixtureMatch $fixtureMatch): Response
    {
        Gate::authorize('update', $fixtureMatch);

        $fixtureMatch->load(['matchday.competitionSeason.season', 'homeClub', 'awayClub']);
        $edition = $fixtureMatch->matchday->competitionSeason;

        return $this->form($request, $edition->season, $fixtureMatch, $edition);
    }

    public function update(MatchRequest $request, FixtureMatch $fixtureMatch, SaveMatch $saveMatch): RedirectResponse
    {
        $saved = $saveMatch->handle($fixtureMatch, $request->matchData(), $this->actor($request));

        return back()->with('toast', $saved->changed
            ? $this->toast($saved, 'sports.toasts.match_updated')
            : Toast::success(Text::get('sports.toasts.nothing_changed')));
    }

    public function cancel(Request $request, FixtureMatch $fixtureMatch, ChangeMatchStatus $changeStatus): RedirectResponse
    {
        return $this->changeStatus($request, $fixtureMatch, $changeStatus, MatchStatus::Cancelled, 'sports.toasts.match_cancelled');
    }

    public function restore(Request $request, FixtureMatch $fixtureMatch, ChangeMatchStatus $changeStatus): RedirectResponse
    {
        return $this->changeStatus($request, $fixtureMatch, $changeStatus, MatchStatus::Scheduled, 'sports.toasts.match_restored');
    }

    private function changeStatus(Request $request, FixtureMatch $match, ChangeMatchStatus $changeStatus, MatchStatus $status, string $toast): RedirectResponse
    {
        Gate::authorize('update', $match);

        $changed = $changeStatus->handle($match, $status, $this->actor($request));
        $match->load(['matchday', 'homeClub', 'awayClub']);

        return back()->with('toast', Toast::success($changed
            ? Text::get($toast, ['match' => $match->label()])
            : Text::get('sports.toasts.nothing_changed')));
    }

    private function form(Request $request, Season $season, ?FixtureMatch $match, CompetitionSeason $edition): Response
    {
        $clubsByEdition = [];

        foreach (CompetitionSeason::query()->where('season_id', $season->id)->get() as $seasonEdition) {
            $clubsByEdition[(string) $seasonEdition->id] = $this->clubOptions($seasonEdition);
        }

        return Inertia::render('Sports/Matches/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_matches'),
            'match' => $match !== null ? [
                'id' => $match->id,
                'label' => $match->label(),
                'competitionSeasonId' => $edition->id,
                'stage' => $match->matchday->stage->value,
                'matchday' => $match->matchday->number,
                'matchDate' => $match->match_date->toDateString(),
                'kickoffTime' => $match->kickoff(),
                'homeClubId' => $match->home_club_id,
                'awayClubId' => $match->away_club_id,
                'stadiumId' => $match->stadium_id,
                'status' => $match->status->value,
                'statusLabel' => $match->status->label(),
                'cancelUrl' => route('sports.matches.cancel', $match),
                'restoreUrl' => route('sports.matches.restore', $match),
            ] : null,
            'season' => ['code' => $season->code, 'startsOn' => $season->starts_on->toDateString(), 'endsOn' => $season->ends_on->toDateString(), 'readOnly' => $season->isReadOnly()],
            'editionId' => (string) $edition->id,
            'editions' => SportsPresenter::editionOptions($season),
            'clubsByEdition' => $clubsByEdition,
            'stages' => array_map(fn (MatchdayStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()], MatchdayStage::cases()),
            'stadiums' => SportsPresenter::stadiumOptions($match?->stadium_id),
            'saveUrl' => $match !== null ? route('sports.matches.update', $match) : route('sports.matches.store'),
            'indexUrl' => PortalPages::url('sports_matches').'?edizione='.$edition->id,
        ]);
    }

    /**
     * Edition of the season chosen in the filter, otherwise the first one of the season.
     */
    private function edition(Season $season, int $id): ?CompetitionSeason
    {
        $editions = CompetitionSeason::query()->where('season_id', $season->id)->with(['competition', 'season'])->get()
            ->sortBy(fn (CompetitionSeason $edition): array => [$edition->competition->sort_order, $edition->competition->name])
            ->values();

        return $editions->firstWhere('id', $id) ?? $editions->first();
    }

    /**
     * Clubs enrolled in an edition, for the selects.
     *
     * @return list<array{value: string, label: string}>
     */
    private function clubOptions(CompetitionSeason $edition): array
    {
        return array_values(ClubEnrollment::query()
            ->where('competition_season_id', $edition->id)
            ->with('club')
            ->get()
            ->sortBy(fn (ClubEnrollment $enrollment): string => mb_strtolower($enrollment->club->short_name))
            ->map(fn (ClubEnrollment $enrollment): array => ['value' => (string) $enrollment->club_id, 'label' => $enrollment->club->short_name])
            ->values()
            ->all());
    }

    /**
     * Success toast, with the warnings that did not block the save (M02 §8).
     *
     * @return array{id: string, type: string, message: string}
     */
    private function toast(SavedMatch $saved, string $key): array
    {
        $message = Text::get($key, ['match' => $saved->match->label()]);

        if ($saved->warnings === []) {
            return Toast::success($message);
        }

        return Toast::error($message.'. '.Text::get('sports.toasts.warning_suffix', ['warnings' => implode('; ', $saved->warnings)]));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
