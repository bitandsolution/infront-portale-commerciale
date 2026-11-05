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
use App\Enums\Role;
use App\Models\Client;
use App\Models\CommoditySector;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * New client (M05 F-01, F-03, FL-02; D-074, D-077, D-358). The agent creates his own clients; the
 * admin creates a client for an active agent he chooses (D-358). Before writing, the duplicate check
 * on the whole registry (RB-02, RB-03, D-197): a duplicate throws DuplicateClient and nothing is
 * written. The logo is checked and re-encoded by FileStorage inside the transaction (D-288).
 * Audit client.created with every field.
 */
final class CreateClient
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly FileStorage $files,
        private readonly DuplicateClients $duplicates,
        private readonly ClientRegistry $registry,
    ) {}

    public function handle(ClientData $data, UploadedFile $logo, User $owner, User $actor): Client
    {
        if ($owner->role !== Role::Agent || ! $owner->is_active) {
            throw ClientsActionNotAllowed::ownerNotAgent();
        }

        return $this->registry->write(function () use ($data, $logo, $owner, $actor): Client {
            $sector = CommoditySector::query()->findOrFail($data->sectorId);

            if (! $sector->is_active) {
                throw ClientsActionNotAllowed::sectorNotActive($sector->name);
            }

            $normalized = CompanyNameNormalizer::normalize($data->companyName);
            $existing = $this->duplicates->find($normalized, $data->vatNumber);

            if ($existing !== null) {
                throw new DuplicateClient($existing, $actor);
            }

            $file = $this->files->storeUpload($logo, FilePurpose::ClientLogo, $actor);

            $client = Client::query()->create([
                'owner_id' => $owner->id,
                'company_name' => $data->companyName,
                'company_name_normalized' => $normalized,
                'vat_number' => $data->vatNumber,
                'email' => $data->email,
                'logo_file_id' => $file->id,
                'commodity_sector_id' => $sector->id,
                'sector_free_text' => $sector->requires_free_text ? $data->sectorFreeText : null,
                'contact_name' => $data->contactName,
                'created_by' => $actor->id,
            ]);

            $this->audit->record(
                AuditAction::ClientCreated,
                $actor,
                AuditSubject::client($client),
                [],
                ClientsAuditValues::client($client, $sector),
            );

            return $client;
        });
    }
}
