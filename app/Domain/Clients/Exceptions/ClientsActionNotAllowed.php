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

    /** D-359: a merged client stays as it is; it is never changed, reassigned or reactivated. */
    public static function merged(string $client): self
    {
        return new self(Text::get('clients.errors.merged', ['client' => $client]));
    }

    /** D-082: the chosen agent already owns the client. */
    public static function alreadyOwner(string $client, string $owner): self
    {
        return new self(Text::get('clients.errors.already_owner', ['client' => $client, 'owner' => $owner]));
    }

    public static function alreadyInactive(string $client): self
    {
        return new self(Text::get('clients.errors.already_inactive', ['client' => $client]));
    }

    public static function alreadyActive(string $client): self
    {
        return new self(Text::get('clients.errors.already_active', ['client' => $client]));
    }

    public static function mergeIntoItself(): self
    {
        return new self(Text::get('clients.errors.merge_into_itself'));
    }

    /** D-359: the client that stays must be active and not merged itself. */
    public static function mergeTargetNotActive(string $client): self
    {
        return new self(Text::get('clients.errors.merge_target_not_active', ['client' => $client]));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
