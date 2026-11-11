<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Actions\CreateSlide;
use App\Domain\Content\Actions\UpdateSlide;
use App\Domain\Content\Exceptions\ContentActionNotAllowed;
use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\SeasonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\SlideRequest;
use App\Models\Club;
use App\Models\Season;
use App\Models\Slide;
use App\Models\User;
use App\Support\Content\ContentPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Contenuti › Libreria slide" (M08 F-01…F-03; D-109, D-402…D-406): admin and manager. The slides of
 * the season of the top bar (D-317), filtered in the browser; new slide and change of a slide, with
 * deactivation instead of deletion (C4). A closed season is read only.
 */
class SlideController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason, ContentPresenter $presenter): Response
    {
        Gate::authorize('viewAny', Slide::class);

        $season = $currentSeason->get();
        $slides = $season === null ? collect() : Slide::query()
            ->with(['section', 'club', 'conditions'])
            ->where('season_id', $season->id)
            ->get();

        return Inertia::render('Content/Slides/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'content_slides'),
            'season' => $season !== null ? ContentPresenter::season($season) : null,
            'slides' => $presenter->rows($slides),
            'clubs' => $slides->pluck('club')->filter()->unique('id')->sortBy('short_name')
                ->map(fn (Club $club): array => ['value' => (string) $club->id, 'label' => $club->short_name])
                ->values()
                ->all(),
            'createUrl' => $season !== null && $season->status !== SeasonStatus::Closed ? route('content.slides.create') : null,
        ]);
    }

    public function create(Request $request, CurrentSeason $currentSeason, ContentPresenter $presenter): Response
    {
        Gate::authorize('create', Slide::class);

        $season = $this->writableSeason($currentSeason->get());

        return Inertia::render('Content/Slides/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'content_slides'),
            'season' => ContentPresenter::season($season),
            'slide' => null,
            'options' => $presenter->options($season),
            'saveUrl' => route('content.slides.store'),
            'indexUrl' => route('pages.content_slides'),
        ]);
    }

    public function store(SlideRequest $request, CreateSlide $createSlide): RedirectResponse
    {
        $season = $this->writableSeason($request->season());
        $slide = $createSlide->handle($season, $request->slideData(), $request->slideImage(), $this->actor($request));

        return redirect()->route('pages.content_slides')->with('toast', Toast::success(
            Text::get('content.toasts.slide_created', ['slide' => $slide->title]),
        ));
    }

    public function edit(Request $request, Slide $slide, ContentPresenter $presenter): Response
    {
        Gate::authorize('update', $slide);

        $slide->load('conditions');
        $season = Season::query()->findOrFail($slide->season_id);

        return Inertia::render('Content/Slides/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'content_slides'),
            'season' => ContentPresenter::season($season),
            'slide' => ContentPresenter::form($slide),
            'options' => $presenter->options($season),
            'saveUrl' => route('content.slides.update', $slide),
            'indexUrl' => route('pages.content_slides'),
        ]);
    }

    public function update(SlideRequest $request, Slide $slide, UpdateSlide $updateSlide): RedirectResponse
    {
        $changed = $updateSlide->handle($slide, $request->slideData(), $request->slideImage(), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('content.toasts.slide_updated', ['slide' => $slide->refresh()->title])
            : Text::get('content.toasts.nothing_changed')));
    }

    private function writableSeason(?Season $season): Season
    {
        if ($season === null) {
            throw ContentActionNotAllowed::noSeason();
        }

        if ($season->status === SeasonStatus::Closed) {
            throw ContentActionNotAllowed::seasonClosed($season->code);
        }

        return $season;
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
