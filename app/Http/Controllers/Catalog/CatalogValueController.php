<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\UpdateCatalogValue;
use App\Enums\CatalogValueKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogValueRequest;
use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\User;
use App\Support\Catalog\CatalogPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page "Catalogo › Valori" (M03 F-01…F-04, F-06; D-331): admin only (Gate catalog.manage on the
 * routes). Values are never created or deleted from the portal: their codes come from the system
 * data (D-332). The admin changes name, order and, where the kind has them, description and active.
 */
class CatalogValueController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Catalog/Values/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'catalog_values'),
            ...CatalogPresenter::values(),
        ]);
    }

    public function editFormat(Request $request, LedFormat $ledFormat): Response
    {
        $ledFormat->load('pools');

        return $this->edit($request, $ledFormat);
    }

    public function editTier(Request $request, PartnershipTier $partnershipTier): Response
    {
        return $this->edit($request, $partnershipTier);
    }

    public function editVariant(Request $request, HospitalityVariant $hospitalityVariant): Response
    {
        return $this->edit($request, $hospitalityVariant);
    }

    public function editCategory(Request $request, BenefitCategory $benefitCategory): Response
    {
        return $this->edit($request, $benefitCategory);
    }

    /*
     * The update actions name the model in their signature, so the route binding resolves it before
     * the form request reads it (CatalogValueRequest::catalogValue()).
     */

    public function updateFormat(CatalogValueRequest $request, LedFormat $ledFormat, UpdateCatalogValue $updateCatalogValue): RedirectResponse
    {
        return $this->update($request, $ledFormat, $updateCatalogValue);
    }

    public function updateTier(CatalogValueRequest $request, PartnershipTier $partnershipTier, UpdateCatalogValue $updateCatalogValue): RedirectResponse
    {
        return $this->update($request, $partnershipTier, $updateCatalogValue);
    }

    public function updateVariant(CatalogValueRequest $request, HospitalityVariant $hospitalityVariant, UpdateCatalogValue $updateCatalogValue): RedirectResponse
    {
        return $this->update($request, $hospitalityVariant, $updateCatalogValue);
    }

    public function updateCategory(CatalogValueRequest $request, BenefitCategory $benefitCategory, UpdateCatalogValue $updateCatalogValue): RedirectResponse
    {
        return $this->update($request, $benefitCategory, $updateCatalogValue);
    }

    private function update(CatalogValueRequest $request, LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value, UpdateCatalogValue $updateCatalogValue): RedirectResponse
    {
        $changed = $updateCatalogValue->handle($value, $request->catalogValueData(), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('catalog.toasts.value_updated', ['value' => $value->refresh()->name])
            : Text::get('catalog.toasts.nothing_changed')));
    }

    private function edit(Request $request, LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value): Response
    {
        $kind = CatalogValueKind::of($value);

        return Inertia::render('Catalog/Values/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'catalog_values'),
            'value' => CatalogPresenter::valueRow($value),
            'kindLabel' => $kind->label(),
            'hasDescription' => $kind->hasDescription(),
            'hasActiveFlag' => $kind->hasActiveFlag(),
            'saveUrl' => route('catalog.values.'.$kind->routeSegment().'.update', $value),
            'indexUrl' => route('pages.catalog_values'),
        ]);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
