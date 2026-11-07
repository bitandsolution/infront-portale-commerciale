<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Clients\Data\ClientsAuditValues;
use App\Domain\Clients\Exceptions\ClientsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Merge of two duplicate clients by the admin (M05 F-08; D-359): the client chosen to stay keeps its
 * own data and owner; the duplicate becomes not active, points to it with merged_into_id and is never
 * deleted (C4). From then on the duplicate is read-only and cannot be reactivated or reassigned.
 * The proposals of the duplicate move to the client that stays with F5, when proposals exist.
 *
 * - The client that stays must be another client, active and not merged itself.
 * - Clients already merged into the duplicate are pointed to the client that stays, so every merged
 *   client points directly to a client that exists (one event client.merged for each of them).
 * - Both rows are locked in id order (D-216), so two merges at the same time cannot cross.
 */
final class MergeClients
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Client $duplicate, Client $keep, User $actor): Client
    {
        if ($duplicate->id === $keep->id) {
            throw ClientsActionNotAllowed::mergeIntoItself();
        }

        return DB::transaction(function () use ($duplicate, $keep, $actor): Client {
            $locked = Client::query()
                ->whereKey([$duplicate->id, $keep->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $duplicate = $locked->get($duplicate->id) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$duplicate->id]);
            $keep = $locked->get($keep->id) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$keep->id]);

            if ($duplicate->isMerged()) {
                throw ClientsActionNotAllowed::merged($duplicate->company_name);
            }

            if ($keep->isMerged() || ! $keep->is_active) {
                throw ClientsActionNotAllowed::mergeTargetNotActive($keep->company_name);
            }

            $this->pointTo($duplicate, $keep, $actor);

            Client::query()
                ->where('merged_into_id', $duplicate->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(fn (Client $earlier) => $this->pointTo($earlier, $keep, $actor));

            return $keep;
        });
    }

    private function pointTo(Client $client, Client $keep, User $actor): void
    {
        $before = ClientsAuditValues::status($client);

        $client->is_active = false;
        $client->merged_into_id = $keep->id;
        $client->setRelation('mergedInto', $keep);
        $client->save();

        [$old, $new] = AuditLog::changes($before, ClientsAuditValues::status($client));

        $this->audit->record(AuditAction::ClientMerged, $actor, AuditSubject::client($client), $old, $new);
    }
}
