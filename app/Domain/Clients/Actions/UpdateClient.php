<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Data\ClientsAuditValues;
use App\Domain\Clients\Exceptions\ClientsActionNotAllowed;
use App\Domain\Clients\Exceptions\DuplicateClient;
use App\Domain\Clients\Services\ClientRegistry;
use App\Domain\Clients\Services\CompanyNameNormalizer;
use App\Domain\Clients\Services\DuplicateClients;
use App\Domain\Files\Services\FileStorage;
use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Models\Client;
use App\Models\CommoditySector;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Change of a client (M05 F-04, FL-03; D-078): the agent changes his own clients, the admin every
 * client (ClientPolicy). The owner does not change here (reassignment with F3-03, D-082).
 *
 * - The duplicate check runs again on the new name and VAT number, without the client itself.
 * - A new logo is a new file: the previous one is never overwritten, so the documents already
 *   generated keep their logo (C10, RB-07, M05-CA5).
 * - A deactivated sector can stay on the client that already has it, but is not chosen anew (RB-08).
 * - Audit client.updated with only the changed fields; nothing is written when nothing changes.
 */
final class UpdateClient
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly FileStorage $files,
        private readonly DuplicateClients $duplicates,
        private readonly ClientRegistry $registry,
    ) {}

    public function handle(Client $client, ClientData $data, ?UploadedFile $logo, User $actor): bool
    {
        return $this->registry->write(function () use ($client, $data, $logo, $actor): bool {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            $before = ClientsAuditValues::client($client, $client->sector);

            $sector = CommoditySector::query()->findOrFail($data->sectorId);

            if (! $sector->is_active && $sector->id !== $client->commodity_sector_id) {
                throw ClientsActionNotAllowed::sectorNotActive($sector->name);
            }

            $normalized = CompanyNameNormalizer::normalize($data->companyName);
            $existing = $this->duplicates->find($normalized, $data->vatNumber, $client->id);

            if ($existing !== null) {
                throw new DuplicateClient($existing, $actor);
            }

            $client->fill([
                'company_name' => $data->companyName,
                'company_name_normalized' => $normalized,
                'vat_number' => $data->vatNumber,
                'email' => $data->email,
                'commodity_sector_id' => $sector->id,
                'sector_free_text' => $sector->requires_free_text ? $data->sectorFreeText : null,
                'contact_name' => $data->contactName,
            ]);

            if ($logo !== null) {
                $client->logo_file_id = $this->files->storeUpload($logo, FilePurpose::ClientLogo, $actor)->id;
            }

            [$old, $new] = AuditLog::changes($before, ClientsAuditValues::client($client, $sector));

            if ($new === []) {
                return false;
            }

            $client->save();

            $this->audit->record(AuditAction::ClientUpdated, $actor, AuditSubject::client($client), $old, $new);

            return true;
        });
    }
}
