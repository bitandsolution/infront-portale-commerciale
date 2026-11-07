<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Clients\Actions\ReassignClient;
use App\Domain\Users\Data\Deactivation;
use App\Domain\Users\Exceptions\UserActionNotAllowed;
use App\Domain\Users\Services\AccessLinks;
use App\Domain\Users\Services\ActiveAdminGuard;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The admin deactivates a user (M01 §5, RB-05): the user cannot sign in any more and his open
 * sessions close at the next request (EnsureUserIsActive); his data stay. Any pending access link
 * stops working.
 *
 * Never the admin himself, never the last active admin (RB-04, M01-CA5): the check runs in the
 * transaction with the rows of the admins locked, so two concurrent deactivations cannot leave the
 * portal without an admin (ActiveAdminGuard).
 *
 * For an agent (M01-CA4, D-082, D-282, D-360) the admin may also choose an active agent who
 * receives all his clients, in the same transaction: each client goes through ReassignClient, with
 * its event client.reassigned; merged clients stay as they are (D-368). The reassignment is
 * optional: without it the clients stay with the deactivated agent, and the admin reassigns them
 * later from the client list (filter "di agenti disattivati"). His proposals stay in his name (D-083).
 *
 * Audit (D-127): user.deactivated, author $actor, in the same transaction.
 */
final class DeactivateUser
{
    public function __construct(
        private readonly ActiveAdminGuard $adminGuard,
        private readonly AccessLinks $links,
        private readonly AuditLog $audit,
        private readonly ReassignClient $reassignClient,
    ) {}

    public function handle(User $user, User $actor, ?User $clientsOwner = null): Deactivation
    {
        if ($actor->is($user)) {
            throw UserActionNotAllowed::ownAccount();
        }

        return DB::transaction(function () use ($user, $actor, $clientsOwner): Deactivation {
            $activeAdmins = $this->adminGuard->lockActiveAdmins();

            // The user and the agent who receives his clients are locked together in id order, so two
            // deactivations that hand clients to each other cannot deadlock (D-216).
            $rows = User::query()
                ->whereKey(array_filter([$user->id, $clientsOwner?->id]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var User $locked */
            $locked = $rows->get($user->id) ?? User::query()->findOrFail($user->id);

            if (! $locked->is_active) {
                return new Deactivation($locked);
            }

            if ($clientsOwner !== null) {
                $clientsOwner = $this->clientsOwner($locked, $rows->get($clientsOwner->id));
            }

            if ($locked->role === Role::Admin) {
                $this->adminGuard->ensureNotLast($locked, $activeAdmins);
            }

            $locked->forceFill([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $actor->id,
            ])->save();

            $this->links->delete($locked);

            $this->audit->record(AuditAction::UserDeactivated, $actor, AuditSubject::user($locked), ['is_active' => true], ['is_active' => false]);

            if ($clientsOwner === null) {
                return new Deactivation($locked);
            }

            $clients = Client::query()
                ->where('owner_id', $locked->id)
                ->whereNull('merged_into_id')
                ->orderBy('id')
                ->get();

            foreach ($clients as $client) {
                $this->reassignClient->handle($client, $clientsOwner, $actor);
            }

            return new Deactivation($locked, $clients->count(), $clientsOwner);
        });
    }

    /**
     * The agent who receives the clients (D-360): only when an agent is deactivated, another active
     * agent (already locked with the user, so he cannot be deactivated at the same time).
     */
    private function clientsOwner(User $user, ?User $owner): User
    {
        if ($user->role !== Role::Agent || $owner === null || $owner->id === $user->id) {
            throw UserActionNotAllowed::invalidClientsOwner();
        }

        if ($owner->role !== Role::Agent || ! $owner->is_active) {
            throw UserActionNotAllowed::invalidClientsOwner();
        }

        return $owner;
    }
}
