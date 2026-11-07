<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Clients\Data\ClientsAuditValues;
use App\Domain\Clients\Exceptions\ClientsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deactivation and reactivation of a client by the admin (M05 F-09, RB-06; M05-CA7 for the part of
 * F3): a client is never deleted (C4), it is deactivated. A deactivated client stays in the registry
 * and in the duplicate check; its owner still sees it, marked "Non attivo"; with F4 it is no longer
 * chosen for a new proposal. A merged client is never reactivated (D-359).
 *
 * Audit client.deactivated / client.reactivated with the status before and after.
 */
final class ChangeClientStatus
{
    public function __construct(private readonly AuditLog $audit) {}

    public function deactivate(Client $client, User $actor): Client
    {
        return $this->change($client, false, $actor);
    }

    public function reactivate(Client $client, User $actor): Client
    {
        return $this->change($client, true, $actor);
    }

    private function change(Client $client, bool $active, User $actor): Client
    {
        return DB::transaction(function () use ($client, $active, $actor): Client {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);

            if ($client->isMerged()) {
                throw ClientsActionNotAllowed::merged($client->company_name);
            }

            if ($client->is_active === $active) {
                throw $active
                    ? ClientsActionNotAllowed::alreadyActive($client->company_name)
                    : ClientsActionNotAllowed::alreadyInactive($client->company_name);
            }

            $before = ClientsAuditValues::status($client);

            $client->is_active = $active;
            $client->save();

            [$old, $new] = AuditLog::changes($before, ClientsAuditValues::status($client));

            $this->audit->record(
                $active ? AuditAction::ClientReactivated : AuditAction::ClientDeactivated,
                $actor,
                AuditSubject::client($client),
                $old,
                $new,
            );

            return $client;
        });
    }
}
