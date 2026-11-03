<?php

namespace App\Domain\Catalog\Exceptions;

use App\Enums\PriceKind;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the catalogue and price lists refuses the operation (M03 §8; D-333). Nothing has been
 * written. In the portal the user goes back to the page with the reason in a toast
 * (ux-architettura §4.5).
 */
final class CatalogActionNotAllowed extends RuntimeException
{
    /** D-333 (as D-317): the price list of a closed season is read-only. */
    public static function seasonClosed(string $season): self
    {
        return new self(Text::get('catalog.errors.season_closed', ['season' => $season]));
    }

    /** D-190: one price list per season. */
    public static function priceListExists(string $season): self
    {
        return new self(Text::get('catalog.errors.price_list_exists', ['season' => $season]));
    }

    /** D-333: publishing is final, a published price list does not go back to draft. */
    public static function alreadyPublished(string $season): self
    {
        return new self(Text::get('catalog.errors.already_published', ['season' => $season]));
    }

    /** D-337: the rows of the grid are the active Infront clubs enrolled in the season. */
    public static function clubNotInGrid(string $club, string $season): self
    {
        return new self(Text::get('catalog.errors.club_not_in_grid', ['club' => $club, 'season' => $season]));
    }

    /** A deactivated value of the catalogue (D-331) is not sold and gets no new prices. */
    public static function valueNotSold(PriceKind $kind, string $name): self
    {
        return new self(Text::get('catalog.errors.not_sold_'.$kind->value, ['value' => $name]));
    }

    /** RB-04: the free package "Libera" has no price and no benefits. */
    public static function freePackage(string $name): self
    {
        return new self(Text::get('catalog.errors.free_package', ['value' => $name]));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
