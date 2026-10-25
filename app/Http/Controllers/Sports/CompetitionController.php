<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\CreateCompetition;
use App\Domain\Sports\Actions\UpdateCompetition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\CompetitionRequest;
use App\Models\Competition;
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
 * Competitions page (M02 F-02; D-137: admin only): list, new competition, change and deactivation
 * (never deletion, C4). Serie A, Serie B and the Serie C groups come from the system data (D-321).
 */
class CompetitionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Competition::class);

        $competitions = Competition::query()
            ->withCount('competitionSeasons')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Competition $competition): array => SportsPresenter::competitionRow($competition, (int) $competition->getAttribute('competition_seasons_count')))
            ->values()
            ->all();

        return Inertia::render('Sports/Competitions/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_competitions'),
            'competitions' => $competitions,
            'createUrl' => route('sports.competitions.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Competition::class);

        return Inertia::render('Sports/Competitions/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_competitions'),
            'competition' => null,
            'types' => SportsPresenter::competitionTypeOptions(),
            'saveUrl' => route('sports.competitions.store'),
            'indexUrl' => route('pages.sports_competitions'),
        ]);
    }

    public function store(CompetitionRequest $request, CreateCompetition $createCompetition): RedirectResponse
    {
        $competition = $createCompetition->handle($request->competitionData(), $this->actor($request));

        return redirect()->route('pages.sports_competitions')->with('toast', Toast::success(
            Text::get('sports.toasts.competition_created', ['competition' => $competition->name]),
        ));
    }

    public function edit(Request $request, Competition $competition): Response
    {
        Gate::authorize('update', $competition);

        return Inertia::render('Sports/Competitions/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_competitions'),
            'competition' => SportsPresenter::competitionRow($competition, $competition->competitionSeasons()->count()),
            'types' => SportsPresenter::competitionTypeOptions(),
            'saveUrl' => route('sports.competitions.update', $competition),
            'indexUrl' => route('pages.sports_competitions'),
        ]);
    }

    public function update(CompetitionRequest $request, Competition $competition, UpdateCompetition $updateCompetition): RedirectResponse
    {
        $changed = $updateCompetition->handle($competition, $request->competitionData(), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('sports.toasts.competition_updated', ['competition' => $competition->name])
            : Text::get('sports.toasts.nothing_changed')));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
