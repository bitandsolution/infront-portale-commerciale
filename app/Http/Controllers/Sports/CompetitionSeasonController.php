<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\AddCompetitionToSeason;
use App\Domain\Sports\Actions\RemoveEdition;
use App\Domain\Sports\Actions\UpdateEdition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\EditionRequest;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\User;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Competitions of a season with their standard number of home matches (D-034, D-185, FL-01), from
 * the page of the season: admin only (D-137, D-321). The rules of a closed season (D-317) and of an
 * edition in use are in the Actions; a refusal comes back as a toast.
 */
class CompetitionSeasonController extends Controller
{
    public function store(EditionRequest $request, Season $season, AddCompetitionToSeason $add): RedirectResponse
    {
        Gate::authorize('create', CompetitionSeason::class);

        $competition = Competition::query()->findOrFail($request->integer('competition_id'));
        $edition = $add->handle($season, $competition, $request->integer('standard_home_matches'), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.edition_created', ['edition' => $edition->label()])));
    }

    public function update(EditionRequest $request, CompetitionSeason $competitionSeason, UpdateEdition $update): RedirectResponse
    {
        Gate::authorize('update', $competitionSeason);

        $changed = $update->handle($competitionSeason, $request->integer('standard_home_matches'), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('sports.toasts.edition_updated', ['edition' => $competitionSeason->label()])
            : Text::get('sports.toasts.nothing_changed')));
    }

    public function destroy(Request $request, CompetitionSeason $competitionSeason, RemoveEdition $remove): RedirectResponse
    {
        Gate::authorize('delete', $competitionSeason);

        $label = $competitionSeason->label();
        $remove->handle($competitionSeason, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.edition_removed', ['edition' => $label])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
