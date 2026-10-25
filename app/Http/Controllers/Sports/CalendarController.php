<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\Ability;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Navigation\PortalPages;
use App\Support\Sports\SportsPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendars page (M02 F-08; D-137: admin and manager): overview of the editions of the season of
 * the top bar (D-317) with enrolled clubs, matches, state of the calendar and standard number of
 * home matches used until the calendar is loaded (D-034).
 */
class CalendarController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason): Response
    {
        Gate::authorize('viewAny', FixtureMatch::class);

        $user = $this->actor($request);
        $season = $currentSeason->get();
        $editions = [];

        if ($season !== null) {
            $editions = CompetitionSeason::query()
                ->where('season_id', $season->id)
                ->with('competition')
                ->withCount('enrollments')
                ->get()
                ->sortBy(fn (CompetitionSeason $edition): array => [$edition->competition->sort_order, $edition->competition->name])
                ->map(function (CompetitionSeason $edition) use ($user): array {
                    $matches = FixtureMatch::query()
                        ->whereHas('matchday', fn ($query) => $query->where('competition_season_id', $edition->id))
                        ->selectRaw('status, COUNT(*) AS total')
                        ->groupBy('status')
                        ->pluck('total', 'status');

                    return [
                        'id' => $edition->id,
                        'competition' => $edition->competition->name,
                        'enrollments' => (int) $edition->getAttribute('enrollments_count'),
                        'scheduled' => (int) ($matches[MatchStatus::Scheduled->value] ?? 0),
                        'cancelled' => (int) ($matches[MatchStatus::Cancelled->value] ?? 0),
                        'standardHomeMatches' => $edition->standard_home_matches,
                        'calendarLoadedAt' => SportsPresenter::dateTime($edition->calendar_loaded_at),
                        'matchesUrl' => PortalPages::url('sports_matches').'?edizione='.$edition->id,
                        'enrollmentsUrl' => Ability::SportsStructureManage->allows($user->role) ? route('sports.editions.show', $edition) : null,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('Sports/Calendars/Index', [
            'layout' => LayoutProps::for($user, 'sports_calendars'),
            'season' => $season !== null ? ['code' => $season->code, 'readOnly' => $season->isReadOnly()] : null,
            'editions' => $editions,
        ]);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
