<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\SetPartnershipBenefits;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\BenefitsRequest;
use App\Models\Club;
use App\Models\PartnershipTier;
use App\Models\PriceList;
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
 * Benefit and on-top texts of a club in a price list (M03 F-08, F-10; D-117, D-335): the admin
 * writes them, the manager consults them (catalog.view on the page, catalog.manage on the writes).
 * Optional texts for the future web landing: the documents use static slides (D-117).
 */
class BenefitController extends Controller
{
    public function show(Request $request, PriceList $priceList, Club $club, PriceListPresenter $presenter): Response
    {
        $user = $this->actor($request);
        $priceList->load(['season', 'publisher']);

        return Inertia::render('Catalog/Benefits/Show', [
            'layout' => LayoutProps::for($user, 'catalog_partnership'),
            ...$presenter->benefitsPage($priceList, $club, Ability::CatalogManage->allows($user->role)),
        ]);
    }

    public function update(BenefitsRequest $request, PriceList $priceList, Club $club, SetPartnershipBenefits $setBenefits): RedirectResponse
    {
        $tier = PartnershipTier::query()->findOrFail($request->integer('partnership_tier_id'));
        $changed = $setBenefits->handle($priceList, $club, $tier, $request->texts(), $this->actor($request));

        return back()->with('toast', Toast::success($changed > 0
            ? Text::get('catalog.toasts.benefits_saved', ['club' => $club->name, 'tier' => $tier->name])
            : Text::get('catalog.toasts.nothing_changed')));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
