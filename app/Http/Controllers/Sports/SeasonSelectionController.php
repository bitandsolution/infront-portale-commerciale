<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\SelectSeason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\SelectSeasonRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Season selector of the top bar (D-317): every role, back to the page where the user was.
 */
class SeasonSelectionController extends Controller
{
    public function __invoke(SelectSeasonRequest $request, SelectSeason $selectSeason): RedirectResponse
    {
        $selectSeason->handle($request->season());

        return back();
    }
}
