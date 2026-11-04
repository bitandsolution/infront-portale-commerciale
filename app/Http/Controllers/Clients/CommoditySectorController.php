<?php

namespace App\Http\Controllers\Clients;

use App\Domain\Clients\Actions\CreateCommoditySector;
use App\Domain\Clients\Actions\UpdateCommoditySector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\CommoditySectorRequest;
use App\Models\CommoditySector;
use App\Models\User;
use App\Support\Clients\ClientsPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sectors page (M05 F-05; D-357: admin only): list, new sector, change and deactivation (never
 * deletion, RB-08).
 */
class CommoditySectorController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CommoditySector::class);

        $sectors = CommoditySector::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CommoditySector $sector): array => ClientsPresenter::sectorRow($sector))
            ->values()
            ->all();

        return Inertia::render('Clients/Sectors/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'clients_sectors'),
            'sectors' => $sectors,
            'createUrl' => route('clients.sectors.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CommoditySector::class);

        $last = CommoditySector::query()->max('sort_order');

        return Inertia::render('Clients/Sectors/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'clients_sectors'),
            'sector' => null,
            'nextSortOrder' => (is_numeric($last) ? (int) $last : 0) + 10,
            'saveUrl' => route('clients.sectors.store'),
            'indexUrl' => route('pages.clients_sectors'),
        ]);
    }

    public function store(CommoditySectorRequest $request, CreateCommoditySector $createSector): RedirectResponse
    {
        $sector = $createSector->handle($request->sectorData(), $this->actor($request));

        return redirect()->route('pages.clients_sectors')->with('toast', Toast::success(
            Text::get('clients.toasts.sector_created', ['sector' => $sector->name]),
        ));
    }

    public function edit(Request $request, CommoditySector $commoditySector): Response
    {
        Gate::authorize('update', $commoditySector);

        return Inertia::render('Clients/Sectors/Form', [
            'layout' => LayoutProps::for($this->actor($request), 'clients_sectors'),
            'sector' => ClientsPresenter::sectorRow($commoditySector),
            'nextSortOrder' => $commoditySector->sort_order,
            'saveUrl' => route('clients.sectors.update', $commoditySector),
            'indexUrl' => route('pages.clients_sectors'),
        ]);
    }

    public function update(CommoditySectorRequest $request, CommoditySector $commoditySector, UpdateCommoditySector $updateSector): RedirectResponse
    {
        $changed = $updateSector->handle($commoditySector, $request->sectorData(), $this->actor($request));

        return back()->with('toast', Toast::success($changed
            ? Text::get('clients.toasts.sector_updated', ['sector' => $commoditySector->refresh()->name])
            : Text::get('clients.toasts.nothing_changed')));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
