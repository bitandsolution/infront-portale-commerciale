<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Clients\Data\ClientsAuditValues;
use App\Domain\Clients\Exceptions\ClientsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reassignment of a client to another agent (M05 F-07, FL-02 step 5; D-082, D-083): only the owner
 * changes. The new owner sees and uses the client, the previous one no longer sees it (M05-CA6);
 * the proposals keep their own agent (D-083, with F5). Active and deactivated clients can be
 * reassigned, a merged client cannot (D-359).
 *
 * The new owner must be an active agent (D-358). Audit client.reassigned with the owner before and
 * after. Runs in its own transaction, or in the caller's (the deactivation of an agent, F3-04).
 */
final class ReassignClient
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Client $client, User $owner, User $actor): Client
    {
        if ($owner->role !== Role::Agent || ! $owner->is_active) {
            throw ClientsActionNotAllowed::ownerNotAgent();
        }

        return DB::transaction(function () use ($client, $owner, $actor): Client {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);

            if ($client->isMerged()) {
                throw ClientsActionNotAllowed::merged($client->company_name);
            }

            if ($client->owner_id === $owner->id) {
                throw ClientsActionNotAllowed::alreadyOwner($client->company_name, $owner->fullName());
            }

            $before = ClientsAuditValues::owner($client);

            $client->owner_id = $owner->id;
            $client->setRelation('owner', $owner);
            $client->save();

            $this->audit->record(
                AuditAction::ClientReassigned,
                $actor,
                AuditSubject::client($client),
                $before,
                ClientsAuditValues::owner($client),
            );

            return $client;
        });
    }
}
