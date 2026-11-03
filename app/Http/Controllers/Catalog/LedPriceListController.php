<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\CreatePriceList;
use App\Domain\Catalog\Actions\PublishPriceList;
use App\Domain\Catalog\Actions\SetLedPrice;
use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreatePriceListRequest;
use App\Http\Requests\Catalog\LedPriceRequest;
use App\Models\Club;
use App\Models\LedFormat;
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
 * Page "Catalogo › Listino LED" (M03 F-05, F-11; D-333, D-337): the grid club × format of the price
 * list of the season of the top bar (D-317). The admin creates the price list, changes the prices
 * cell by cell and publishes it; the manager only consults it (D-137). Gates on the routes:
 * catalog.view for the page, catalog.manage for the writes.
 */
class LedPriceListController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason, PriceListPresenter $presenter): Response
    {
        $user = $this->actor($request);
        $season = $currentSeason->get();
        $priceList = $season === null ? null : PriceList::query()->with('publisher')->where('season_id', $season->id)->first();

        return Inertia::render('Catalog/Led/Index', [
            'layout' => LayoutProps::for($user, 'catalog_led'),
            'page' => $season === null ? null : $presenter->ledPage($season, $priceList, Ability::CatalogManage->allows($user->role)),
        ]);
    }

    public function store(CreatePriceListRequest $request, CreatePriceList $createPriceList): RedirectResponse
    {
        $season = Season::query()->findOrFail($request->integer('season_id'));
        $createPriceList->handle($season, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.price_list_created', ['season' => $season->code])));
    }

    /*
     * The price list is named in the signature, so the route binding resolves it (F2-01, #57).
     */

    public function updatePrice(LedPriceRequest $request, PriceList $priceList, SetLedPrice $setLedPrice): RedirectResponse
    {
        $club = Club::query()->findOrFail($request->integer('club_id'));
        $format = LedFormat::query()->findOrFail($request->integer('led_format_id'));

        $change = $setLedPrice->handle($priceList, $club, $format, $request->unitPrice(), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.led_price_'.$change->value, [
            'club' => $club->name,
            'format' => $format->name,
        ])));
    }

    public function publish(Request $request, PriceList $priceList, PublishPriceList $publishPriceList): RedirectResponse
    {
        $published = $publishPriceList->handle($priceList, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('catalog.toasts.price_list_published', ['season' => $published->season->code])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
