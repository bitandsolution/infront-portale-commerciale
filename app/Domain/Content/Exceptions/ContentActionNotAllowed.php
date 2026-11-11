<?php

namespace App\Domain\Content\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the contents refuses the operation (M08). Nothing has been written. In the portal the
 * user goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class ContentActionNotAllowed extends RuntimeException
{
    /** D-317: the contents of a closed season are read only, like its price list. */
    public static function seasonClosed(string $season): self
    {
        return new self(Text::get('content.errors.season_closed', ['season' => $season]));
    }

    public static function noSeason(): self
    {
        return new self(Text::get('content.errors.no_season'));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
