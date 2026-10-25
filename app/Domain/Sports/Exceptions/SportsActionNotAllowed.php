<?php

namespace App\Domain\Sports\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the sports registry refuses the operation (M02 §8; D-317). Nothing has been written.
 * In the portal the user goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class SportsActionNotAllowed extends RuntimeException
{
    /** M02 §8: seasons of the same discipline never overlap. */
    public static function seasonOverlaps(string $otherCode): self
    {
        return new self(Text::get('sports.errors.season_overlaps', ['season' => $otherCode]));
    }

    /** D-317: the sports registry of a closed season is read-only. */
    public static function seasonClosed(string $code): self
    {
        return new self(Text::get('sports.errors.season_closed', ['season' => $code]));
    }

    public static function editionExists(string $label): self
    {
        return new self(Text::get('sports.errors.edition_exists', ['edition' => $label]));
    }

    /** An edition with enrollments, matches or imports is never removed (C4, FK RESTRICT). */
    public static function editionInUse(string $label): self
    {
        return new self(Text::get('sports.errors.edition_in_use', ['edition' => $label]));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
