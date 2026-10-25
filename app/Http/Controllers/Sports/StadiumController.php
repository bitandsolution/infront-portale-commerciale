<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\CreateStadium;
use App\Domain\Sports\Actions\UpdateStadium;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\StadiumRequest;
use App\Models\Stadium;
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
 * Stadiums page (M02 F-07; D-137: admin and manager): list, new stadium, change and deactivation
 * (never deletion, C4).
 */
class StadiumController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Stadium::class);

        $stadiums = Stadium::query()
            ->withCount('clubs')
            ->orderBy('name')
            ->get()
            ->map(fn (Stadium $stadium): array => SportsPresenter::stadiumRow($stadium, (int) $stadium->getAttribute('clubs_count')))
            ->values()
            ->all();

        return Inertia::render('Sports/Stadiums/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_stadiums'),
            'stadiums' => $stadiums,
            'createUrl' => route('sports.stadiums.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Stadium::class);

        return Inertia::render('Sports/Stadiums/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_stadiums'),
            'stadium' => null,
            'saveUrl' => route('sports.stadiums.store'),
            'indexUrl' => route('pages.sports_stadiums'),
        ]);
    }

    public function store(StadiumRequest $request, CreateStadium $createStadium): RedirectResponse
    {
        $stadium = $createStadium->handle($request->stadiumData(), $this->actor($request));

        return redirect()->route('pages.sports_stadiums')->with('toast', Toast::success(
            Text::get('sports.toasts.stadium_created', ['stadium' => $stadium->name]),
        ));
    }

    public function edit(Request $request, Stadium $stadium): Response
    {
        Gate::authorize('update', $stadium);

        return Inertia::render('Sports/Stadiums/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_stadiums'),
            'stadium' => SportsPresenter::stadiumRow($stadium, $stadium->clubs()->count()),
            'saveUrl' => route('sports.stadiums.update', $stadium),
            'indexUrl' => route('pages.sports_stadiums'),
        ]);
    }

    public function update(StadiumRequest $request, Stadium $stadium, UpdateStadium $updateStadium): RedirectResponse
    {
        $changed = $updateStadium->handle($stadium, $request->stadiumData(), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('sports.toasts.stadium_updated', ['stadium' => $stadium->refresh()->name])
            : Text::get('sports.toasts.nothing_changed')));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
