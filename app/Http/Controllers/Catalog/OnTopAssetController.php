<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\SaveOnTopAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\OnTopAssetRequest;
use App\Models\Club;
use App\Models\OnTopAsset;
use App\Models\PriceList;
use App\Models\User;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * On-top texts of a price list (M03 F-10; D-049, D-335): added, changed and removed by the admin
 * from the page "Partnership" (general texts) or from the page of a club. Gate catalog.manage on the
 * routes.
 */
class OnTopAssetController extends Controller
{
    public function store(OnTopAssetRequest $request, PriceList $priceList, SaveOnTopAsset $saveOnTopAsset): RedirectResponse
    {
        $club = $request->filled('club_id') ? Club::query()->findOrFail($request->integer('club_id')) : null;
        $asset = $saveOnTopAsset->create($priceList, $club, $request->titleText(), $request->bodyText(), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.on_top_created', ['title' => $asset->title])));
    }

    public function update(OnTopAssetRequest $request, OnTopAsset $onTopAsset, SaveOnTopAsset $saveOnTopAsset): RedirectResponse
    {
        $changed = $saveOnTopAsset->update($onTopAsset, $request->titleText(), $request->bodyText(), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('catalog.toasts.on_top_updated', ['title' => $request->titleText()])
            : Text::get('catalog.toasts.nothing_changed')));
    }

    public function destroy(Request $request, OnTopAsset $onTopAsset, SaveOnTopAsset $saveOnTopAsset): RedirectResponse
    {
        $title = $onTopAsset->title;
        $saveOnTopAsset->remove($onTopAsset, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.on_top_removed', ['title' => $title])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
