<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;

/**
 * Clients (M05; M01 §4 matrix, D-076, D-078, D-082). The agent sees and changes only his own clients
 * (RB-01); the manager sees every client, read-only; the admin sees and changes every client.
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

    public function update(User $user, Client $client): bool
    {
        return Ability::ClientsManageAll->allows($user->role) || $this->ownsAsAgent($user, $client);
    }

    private function ownsAsAgent(User $user, Client $client): bool
    {
        return $user->role === Role::Agent && Ability::ClientsOwn->allows($user->role) && $client->isOwnedBy($user);
    }
}
