<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\AddClubAlias;
use App\Domain\Sports\Actions\ChangeClubLogo;
use App\Domain\Sports\Actions\CreateClub;
use App\Domain\Sports\Actions\RemoveClubAlias;
use App\Domain\Sports\Actions\UpdateClub;
use App\Enums\AliasSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\ClubAliasRequest;
use App\Http\Requests\Sports\ClubLogoRequest;
use App\Http\Requests\Sports\ClubRequest;
use App\Models\Club;
use App\Models\ClubAlias;
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
 * Clubs page (M02 F-05, F-11; D-137: admin only): Infront clubs and opponents, data and flags,
 * logo, aliases. Clubs are deactivated, never deleted (C4, RB-08, M02-CA7). About 90 clubs: the
 * whole list arrives from the server and the filters work in the browser.
 */
class ClubController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Club::class);

        $clubs = Club::query()
            ->with('homeStadium')
            ->orderBy('name')
            ->get()
            ->map(fn (Club $club): array => SportsPresenter::clubRow($club))
            ->values()
            ->all();

        return Inertia::render('Sports/Clubs/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_clubs'),
            'clubs' => $clubs,
            'createUrl' => route('sports.clubs.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Club::class);

        return Inertia::render('Sports/Clubs/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_clubs'),
            'club' => null,
            'stadiums' => SportsPresenter::stadiumOptions(),
            'saveUrl' => route('sports.clubs.store'),
            'indexUrl' => route('pages.sports_clubs'),
            'logoUrl' => null,
            'aliases' => [],
            'addAliasUrl' => null,
        ]);
    }

    public function store(ClubRequest $request, CreateClub $createClub): RedirectResponse
    {
        $club = $createClub->handle($request->clubData(), $this->actor($request));

        return redirect()->route('sports.clubs.edit', $club)->with('toast', Toast::success(
            Text::get('sports.toasts.club_created', ['club' => $club->name]),
        ));
    }

    public function edit(Request $request, Club $club): Response
    {
        Gate::authorize('update', $club);

        $club->load(['homeStadium', 'aliases']);

        return Inertia::render('Sports/Clubs/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_clubs'),
            'club' => SportsPresenter::clubRow($club),
            'stadiums' => SportsPresenter::stadiumOptions($club->home_stadium_id),
            'saveUrl' => route('sports.clubs.update', $club),
            'indexUrl' => route('pages.sports_clubs'),
            'logoUrl' => route('sports.clubs.logo', $club),
            'aliases' => $club->aliases
                ->sortBy(fn (ClubAlias $alias): string => mb_strtolower($alias->alias))
                ->map(fn (ClubAlias $alias): array => SportsPresenter::aliasRow($alias))
                ->values()
                ->all(),
            'addAliasUrl' => route('sports.clubs.aliases.store', $club),
        ]);
    }

    public function update(ClubRequest $request, Club $club, UpdateClub $updateClub): RedirectResponse
    {
        $changed = $updateClub->handle($club, $request->clubData(), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('sports.toasts.club_updated', ['club' => $club->refresh()->name])
            : Text::get('sports.toasts.nothing_changed')));
    }

    public function logo(ClubLogoRequest $request, Club $club, ChangeClubLogo $changeLogo): RedirectResponse
    {
        $changeLogo->handle($club, $request->logo(), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.club_logo_changed', ['club' => $club->name])));
    }

    public function storeAlias(ClubAliasRequest $request, Club $club, AddClubAlias $addAlias): RedirectResponse
    {
        $alias = $addAlias->handle($club, $request->string('alias')->toString(), AliasSource::Manual, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.alias_created', ['alias' => $alias->alias])));
    }

    public function destroyAlias(Request $request, ClubAlias $clubAlias, RemoveClubAlias $removeAlias): RedirectResponse
    {
        Gate::authorize('update', $clubAlias->club);

        $text = $clubAlias->alias;
        $removeAlias->handle($clubAlias, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.alias_removed', ['alias' => $text])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
