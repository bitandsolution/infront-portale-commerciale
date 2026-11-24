<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Services\CompositionSimulator;
use App\Domain\Sports\Services\CurrentSeason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\SimulatorRequest;
use App\Models\Slide;
use App\Models\User;
use App\Support\Content\SimulatorPresenter;
use App\Support\Navigation\LayoutProps;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Contenuti › Simulatore" (M08 F-04, FL-01, CA5; D-494…D-498): admin and manager, season of the top
 * bar, also a closed one (nothing is written). The choices are in the address; the result is the
 * composition of DocumentComposer through CompositionSimulator. No Action and no audit: nothing is
 * saved.
 */
class SimulatorController extends Controller
{
    public function index(SimulatorRequest $request, CurrentSeason $currentSeason, CompositionSimulator $simulator, SimulatorPresenter $presenter): Response
    {
        Gate::authorize('viewAny', Slide::class);

        /** @var User $user */
        $user = $request->user();
        $season = $currentSeason->get();
        $layout = LayoutProps::for($user, 'content_simulator');

        if ($season === null) {
            return Inertia::render('Content/Simulator', ['layout' => $layout, 'season' => null, 'options' => null, 'choice' => null, 'result' => null, 'url' => route('pages.content_simulator')]);
        }

        $choices = $request->choices();
        $mode = $request->mode();
        $choice = $presenter->choice($season, $choices, $mode);

        return Inertia::render('Content/Simulator', [
            'layout' => $layout,
            'season' => $presenter->season($season),
            'options' => $presenter->options($season),
            'choice' => $choice,
            'result' => $choice['clubs'] === [] ? null : $presenter->result($simulator->compose($season, $choices, $mode)),
            'url' => route('pages.content_simulator'),
        ]);
    }
}
