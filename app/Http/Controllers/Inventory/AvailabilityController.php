<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Inventory\AvailabilityPresenter;
use App\Support\Navigation\LayoutProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page "Magazzino › Disponibilità" (M04 F-06; mockup "Magazzino"; D-137, D-337; F2-05): admin and
 * manager (Gate inventory.view on the route), read only. The link to the capacities of a club is
 * offered only to who manages them (admin, D-137).
 */
class AvailabilityController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason, AvailabilityPresenter $presenter): Response
    {
        /** @var User $user */
        $user = $request->user();
        $season = $currentSeason->get();

        return Inertia::render('Inventory/Availability/Index', [
            'layout' => LayoutProps::for($user, 'inventory_availability'),
            'page' => $season === null ? null : $presenter->page($season, Ability::InventoryCapacityManage->allows($user->role)),
        ]);
    }
}
