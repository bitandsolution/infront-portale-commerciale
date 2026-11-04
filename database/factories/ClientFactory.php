<?php

namespace Database\Factories;

use App\Domain\Clients\Services\CompanyNameNormalizer;
use App\Domain\Files\Services\FileStorage;
use App\Enums\FilePurpose;
use App\Models\Client;
use App\Models\CommoditySector;
use App\Models\User;
use App\Support\Files\SampleFiles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * Fictitious clients owned by an agent, with a PNG logo stored through FileStorage (the tests need
 * Storage::fake(StoredFile::DISK)) and no VAT number.
 *
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Azienda di prova '.fake()->unique()->numberBetween(1, 99999).' Srl';
        $owner = User::factory()->agent();

        return [
            'owner_id' => $owner,
            'company_name' => $name,
            'company_name_normalized' => CompanyNameNormalizer::normalize($name),
            'vat_number' => null,
            'email' => fake()->unique()->safeEmail(),
            'logo_file_id' => fn (): int => DB::transaction(fn (): int => app(FileStorage::class)
                ->storeContents(SampleFiles::png(400, 200), FilePurpose::ClientLogo, null, 'logo-prova.png')->id),
            'commodity_sector_id' => CommoditySector::factory(),
            'sector_free_text' => null,
            'contact_name' => null,
            'is_active' => true,
            'merged_into_id' => null,
            'created_by' => fn (array $attributes): mixed => $attributes['owner_id'],
        ];
    }

    /**
     * A client with this company name (normalized as the Actions do).
     */
    public function named(string $name, ?string $vatNumber = null): self
    {
        return $this->state([
            'company_name' => $name,
            'company_name_normalized' => CompanyNameNormalizer::normalize($name),
            'vat_number' => $vatNumber,
        ]);
    }

    public function ownedBy(User $owner): self
    {
        return $this->state(['owner_id' => $owner->id, 'created_by' => $owner->id]);
    }
}
