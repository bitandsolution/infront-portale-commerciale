<?php

namespace App\Domain\Clients\Exceptions;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * The client is already in the registry (M05 F-03, RB-02, RB-03; D-077, D-080, D-197). Nothing has
 * been written. The form shows a blocking dialog (ux-architettura §4.5, "Cliente duplicato").
 *
 * The agent gets a neutral message that never says whose client it is (FL-02 step 4); the admin,
 * who sees every client anyway, also gets its name and owner, so he can assign it (D-082).
 */
final class DuplicateClient extends RuntimeException
{
    public function __construct(public readonly Client $existing, User $actor)
    {
        parent::__construct($actor->role === Role::Admin
            ? Text::get('clients.errors.duplicate_admin', [
                'client' => $existing->company_name,
                'owner' => $existing->owner->fullName(),
            ])
            : Text::get('clients.errors.duplicate'));
    }

    public function render(): RedirectResponse
    {
        return back()->withErrors(['duplicate' => $this->getMessage()]);
    }
}
