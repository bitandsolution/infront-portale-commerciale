<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;

/**
 * Clients (M05; M01 §4 matrix, D-076, D-078, D-082). The agent sees and changes only his own clients
 * (RB-01); the manager sees every client, read-only; the admin sees and changes every client and
 * is the only one who reassigns, deactivates and merges clients (F3-03).
 * Queries go through Client::visibleTo() as a second line of defence (HasOwner). Deactivated users
 * are already refused by Gate::before (AppServiceProvider).
 */
class ClientPolicy
{
    /** A list of clients: "I miei clienti" for the agent, "Elenco clienti" for the back office. */
    public function viewAny(User $user): bool
    {
        return Ability::ClientsOwn->allows($user->role) || Ability::ClientsViewAll->allows($user->role);
    }

    public function view(User $user, Client $client): bool
    {
        return Ability::ClientsViewAll->allows($user->role) || $this->ownsAsAgent($user, $client);
    }

    public function create(User $user): bool
    {
        return Ability::ClientsOwn->allows($user->role);
    }

    /** A merged client is read-only for everyone (D-359). */
    public function update(User $user, Client $client): bool
    {
        return ! $client->isMerged()
            && (Ability::ClientsManageAll->allows($user->role) || $this->ownsAsAgent($user, $client));
    }

    /**
     * Reassignment, deactivation, reactivation and merge (M05 F-07, F-08, F-09; D-082, D-359): only
     * the admin. The Actions refuse what a merged client no longer allows.
     */
    public function manage(User $user, Client $client): bool
    {
        return Ability::ClientsManageAll->allows($user->role);
    }

    private function ownsAsAgent(User $user, Client $client): bool
    {
        return $user->role === Role::Agent && Ability::ClientsOwn->allows($user->role) && $client->isOwnedBy($user);
    }
}
