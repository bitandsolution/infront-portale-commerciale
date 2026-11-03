<?php

namespace App\Domain\Inventory\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the inventory refuses the operation (M04 §8, §9; D-293, D-337). Nothing has been
 * written. In the portal the user goes back to the page with the reason in a toast.
 */
final class InventoryActionNotAllowed extends RuntimeException
{
    /** D-293, D-337: only the seasonal method is implemented; "per match" is a future evolution. */
    public static function methodNotSupported(string $method): self
    {
        return new self(Text::get('inventory.errors.method_not_supported', ['method' => $method]));
    }

    /** As D-317 and D-333: the inventory of a closed season is read-only. */
    public static function seasonClosed(string $season): self
    {
        return new self(Text::get('inventory.errors.season_closed', ['season' => $season]));
    }

    /** D-337: the rows are the active Infront clubs enrolled in the season. */
    public static function clubNotInGrid(string $club, string $season): self
    {
        return new self(Text::get('inventory.errors.club_not_in_grid', ['club' => $club, 'season' => $season]));
    }

    /** M04 §9, D-063: LED capacities in multiples of the step of the settings. */
    public static function stepNotRespected(string $pool, string $step): self
    {
        return new self(Text::get('inventory.errors.step_not_respected', ['pool' => $pool, 'step' => $step]));
    }

    /** Only the active values of the catalogue have a capacity; the free package has no counter (D-052). */
    public static function valueNotManaged(string $name): self
    {
        return new self(Text::get('inventory.errors.value_not_managed', ['value' => $name]));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
