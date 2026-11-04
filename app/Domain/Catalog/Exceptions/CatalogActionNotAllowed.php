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

    /** M03 RB-09: a proposal uses only the published price list of its season. */
    public static function priceListNotPublished(string $season): self
    {
        return new self(Text::get('catalog.errors.price_list_not_published', ['season' => $season]));
    }

    /** D-190, M03-CA4: an empty cell of the grid is "not available" for the club. */
    public static function priceNotAvailable(string $value, string $club, string $season): self
    {
        return new self(Text::get('catalog.errors.price_not_available', ['value' => $value, 'club' => $club, 'season' => $season]));
    }

    /** D-063: the LED minutes go in steps of the settings. */
    public static function ledStep(string $club, string $step): self
    {
        return new self(Text::get('catalog.errors.led_step', ['club' => $club, 'step' => $step]));
    }

    /** D-147: at most the seats of the settings per club in a proposal. */
    public static function tooManySeats(string $club, int $max): self
    {
        return new self(Text::get('catalog.errors.too_many_seats', ['club' => $club, 'max' => (string) $max]));
    }

    /** M06 §4, D-088: one row per club, one format per club, seats from 1, one line per variant. */
    public static function invalidSelection(): self
    {
        return new self(Text::get('catalog.errors.invalid_selection'));
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
