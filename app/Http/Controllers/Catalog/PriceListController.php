<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\CreatePriceList;
use App\Domain\Catalog\Actions\PublishPriceList;
use App\Domain\Catalog\Actions\SetPrice;
use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\Ability;
use App\Enums\PriceKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreatePriceListRequest;
use App\Http\Requests\Catalog\PriceCellRequest;
use App\Models\Club;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use App\Support\Catalog\PriceListPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages "Catalogo › Listini LED", "Partnership" and "Hospitality" (M03 F-05, F-07, F-09, F-11;
 * D-333, D-337): the grids club × value of the price list of the season of the top bar (D-317).
 * The admin creates the price list, changes the prices cell by cell and publishes it from any of
 * the three pages; the manager only consults them (D-137). Gates on the routes: catalog.view for
 * the pages, catalog.manage for the writes.
 */
class PriceListController extends Controller
{
    public function led(Request $request, CurrentSeason $currentSeason, PriceListPresenter $presenter): Response
    {
        return $this->page($request, PriceKind::Led, $currentSeason, $presenter);
    }

    public function partnership(Request $request, CurrentSeason $currentSeason, PriceListPresenter $presenter): Response
    {
        return $this->page($request, PriceKind::Partnership, $currentSeason, $presenter);
    }

    public function hospitality(Request $request, CurrentSeason $currentSeason, PriceListPresenter $presenter): Response
    {
        return $this->page($request, PriceKind::Hospitality, $currentSeason, $presenter);
    }

    public function store(CreatePriceListRequest $request, CreatePriceList $createPriceList): RedirectResponse
    {
        $season = Season::query()->findOrFail($request->integer('season_id'));
        $createPriceList->handle($season, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.price_list_created', ['season' => $season->code])));
    }

    /*
     * The price list and the kind are named in the signature, so the route binding resolves them
     * (F2-01, #57): an unknown kind is a 404.
     */

    public function updatePrice(PriceCellRequest $request, PriceList $priceList, PriceKind $kind, SetPrice $setPrice): RedirectResponse
    {
        $club = Club::query()->findOrFail($request->integer('club_id'));
        $value = $kind->columnModel()::query()->findOrFail($request->integer('column_id'));

        $change = $setPrice->handle($priceList, $kind, $club, $value, $request->amount(), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.price_'.$change->value, [
            'club' => $club->name,
            'value' => $value->name,
        ])));
    }

    public function publish(Request $request, PriceList $priceList, PublishPriceList $publishPriceList): RedirectResponse
    {
        $published = $publishPriceList->handle($priceList, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.price_list_published', ['season' => $published->season->code])));
    }

    private function page(Request $request, PriceKind $kind, CurrentSeason $currentSeason, PriceListPresenter $presenter): Response
    {
        $user = $this->actor($request);
        $season = $currentSeason->get();
        $priceList = $season === null ? null : PriceList::query()->with(['publisher', 'season'])->where('season_id', $season->id)->first();

        return Inertia::render('Catalog/PriceList/Index', [
            'layout' => LayoutProps::for($user, $kind->pageKey()),
            'page' => $season === null ? null : $presenter->gridPage($kind, $season, $priceList, Ability::CatalogManage->allows($user->role)),
        ]);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
