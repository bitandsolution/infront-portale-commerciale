<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\EnrollClubs;
use App\Domain\Sports\Actions\RemoveEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\EnrollClubsRequest;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clubs enrolled in an edition (M02 F-06, FL-01 step 2; D-137, D-321: admin only), reached from the
 * page of the season. One league per club and season and the closed season (D-317) are checked by
 * the Actions; a refusal comes back as a toast.
 */
class EditionController extends Controller
{
    public function show(Request $request, CompetitionSeason $competitionSeason): Response
    {
        Gate::authorize('update', $competitionSeason);

        $edition = $competitionSeason->load(['competition', 'season']);
        $enrollments = ClubEnrollment::query()
            ->where('competition_season_id', $edition->id)
            ->with('club')
            ->get()
            ->sortBy(fn (ClubEnrollment $enrollment): string => mb_strtolower($enrollment->club->name))
            ->values();

        $available = Club::query()
            ->where('is_active', true)
            ->whereNotIn('id', $enrollments->pluck('club_id'))
            ->orderBy('name')
            ->get()
            ->map(fn (Club $club): array => ['value' => (string) $club->id, 'label' => $club->name.($club->is_infront ? ' · Infront' : '')])
            ->values()
            ->all();

        return Inertia::render('Sports/Editions/Show', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_seasons'),
            'edition' => [
                'id' => $edition->id,
                'label' => $edition->label(),
                'standardHomeMatches' => $edition->standard_home_matches,
                'readOnly' => $edition->season->isReadOnly(),
            ],
            'enrollments' => $enrollments->map(fn (ClubEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'clubName' => $enrollment->club->name,
                'clubCode' => $enrollment->club->code,
                'isInfront' => $enrollment->club->is_infront,
                'logoUrl' => $enrollment->club->logo_file_id !== null ? route('files.download', $enrollment->club->logo_file_id) : null,
                'destroyUrl' => route('sports.enrollments.destroy', $enrollment),
            ])->all(),
            'availableClubs' => $available,
            'enrollUrl' => route('sports.enrollments.store', $edition),
            'seasonUrl' => route('sports.seasons.edit', $edition->season),
        ]);
    }

    public function enroll(EnrollClubsRequest $request, CompetitionSeason $competitionSeason, EnrollClubs $enrollClubs): RedirectResponse
    {
        Gate::authorize('create', ClubEnrollment::class);

        $enrolled = $enrollClubs->handle($competitionSeason, $request->clubIds(), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.clubs_enrolled', [
            'edition' => $competitionSeason->label(),
            'count' => (string) count($enrolled),
        ])));
    }

    public function destroy(Request $request, ClubEnrollment $clubEnrollment, RemoveEnrollment $removeEnrollment): RedirectResponse
    {
        Gate::authorize('delete', $clubEnrollment);

        $club = $clubEnrollment->club->name;
        $edition = $clubEnrollment->competitionSeason->label();
        $removeEnrollment->handle($clubEnrollment, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.enrollment_removed', ['club' => $club, 'edition' => $edition])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
