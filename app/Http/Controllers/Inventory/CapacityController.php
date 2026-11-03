<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Inventory\Actions\SetClubCapacities;
use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Exceptions\CapacityBelowCommitted;
use App\Domain\Inventory\Exceptions\InventoryActionNotAllowed;
use App\Domain\Settings\Services\Settings;
use App\Domain\Sports\Services\CurrentSeason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ClubCapacitiesRequest;
use App\Models\Club;
use App\Models\Season;
use App\Models\User;
use App\Support\Inventory\InventoryPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages "Magazzino › Capacità" (M04 F-01…F-03, FL-01; D-191, D-337): admin only (D-137; Gate
 * inventory.capacity.manage on the routes). The list of the clubs of the season of the top bar with
 * their capacities, and the form of a club with committed and residual of every resource.
 */
class CapacityController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason, InventoryPresenter $presenter): Response
    {
        $season = $currentSeason->get();

        return Inertia::render('Inventory/Capacities/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'inventory_capacity'),
            'page' => $season === null ? null : $presenter->capacitiesPage($season),
        ]);
    }

    public function edit(Request $request, Club $club, CurrentSeason $currentSeason, PriceListGrid $grid, Settings $settings, InventoryPresenter $presenter): Response|RedirectResponse
    {
        $season = $request->filled('stagione')
            ? Season::query()->findOrFail($request->integer('stagione'))
            : $currentSeason->get();

        if ($season === null || ! $grid->hasClub($season, $club)) {
            throw InventoryActionNotAllowed::clubNotInGrid($club->name, $season === null ? '—' : $season->code);
        }

        return Inertia::render('Inventory/Capacities/Edit', [
            'layout' => LayoutProps::for($this->actor($request), 'inventory_capacity'),
            ...$presenter->clubPage($season, $club, $settings->ledStepSeconds()),
        ]);
    }

    public function update(ClubCapacitiesRequest $request, Club $club, SetClubCapacities $setCapacities): RedirectResponse
    {
        $season = Season::query()->findOrFail($request->integer('season_id'));

        try {
            $result = $setCapacities->handle($season, $club, $request->capacityInputs(), $request->boolean('confirmed'), $this->actor($request));
        } catch (CapacityBelowCommitted $below) {
            return back()->withErrors(['confirm' => Text::get('inventory.confirm_below', ['resources' => self::describe($below->resources)])]);
        }

        $message = match (true) {
            $result['changed'] === 0 => Text::get('inventory.toasts.nothing_changed'),
            $result['belowCommitted'] !== [] => Text::get('inventory.toasts.capacities_saved_below', ['club' => $club->name, 'resources' => self::describe($result['belowCommitted'])]),
            default => Text::get('inventory.toasts.capacities_saved', ['club' => $club->name]),
        };

        return back()->with('toast', Toast::success($message));
    }

    /**
     * @param  list<ResourceAvailability>  $resources
     */
    private static function describe(array $resources): string
    {
        return implode(', ', array_map(fn (ResourceAvailability $item): string => $item->isEnabled
            ? Text::get('inventory.below_item', [
                'name' => $item->name,
                'committed' => InventoryPresenter::quantity($item->resource, $item->committed),
                'capacity' => InventoryPresenter::quantity($item->resource, $item->capacity),
            ])
            : Text::get('inventory.below_item_not_managed', [
                'name' => $item->name,
                'committed' => InventoryPresenter::quantity($item->resource, $item->committed),
            ]), $resources));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
