<?php

namespace App\Domain\Clients\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the clients registry refuses the operation (M05 §7). Nothing has been written. In the
 * portal the user goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class ClientsActionNotAllowed extends RuntimeException
{
    /** D-076, D-358: a client always belongs to an active agent. */
    public static function ownerNotAgent(): self
    {
        return new self(Text::get('clients.errors.owner_not_agent'));
    }

    /** RB-08: a deactivated sector is no longer chosen for a client (the current one stays). */
    public static function sectorNotActive(string $sector): self
    {
        return new self(Text::get('clients.errors.sector_not_active', ['sector' => $sector]));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
