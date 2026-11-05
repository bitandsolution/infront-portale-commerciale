<?php

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Rules\ItalianVatNumber;
use App\Domain\Clients\Services\VatNumber;
use App\Domain\Files\Rules\AcceptableFile;
use App\Enums\FilePurpose;
use App\Enums\Role;
use App\Models\Client;
use App\Models\CommoditySector;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * New client or change of a client (M05 §6, §8; D-075, D-079, D-081, D-358, D-361):
 * - company name of at least 2 characters, e-mail, sector and logo required (the logo only for a
 *   new client: on a change it stays the current one if no file is chosen);
 * - free text required with a sector that asks for it (RB-04);
 * - VAT number optional, Italian with the check digit (ItalianVatNumber);
 * - the admin chooses the owner among the active agents when he creates a client (D-358).
 * The duplicate check is in the Actions (DuplicateClients), under the registry lock.
 */
class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->client();

        return $client !== null
            ? $this->user()?->can('update', $client) ?? false
            : $this->user()?->can('create', Client::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $client = $this->client();

        $rules = [
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:20', new ItalianVatNumber],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'commodity_sector_id' => ['bail', 'required', 'integer', Rule::exists('commodity_sectors', 'id')->where(
                // Active sectors, plus the current sector of the client, which can stay (RB-08).
                fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('is_active', true)
                    ->when($client !== null, fn (Builder $query) => $query->orWhere('id', $client?->commodity_sector_id))),
            )],
            'sector_free_text' => [Rule::requiredIf(fn (): bool => $this->sector()?->requires_free_text === true), 'nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'logo' => [$client === null ? 'required' : 'nullable', 'bail', 'file', new AcceptableFile(FilePurpose::ClientLogo)],
        ];

        if ($client === null && $this->actor()->role === Role::Admin) {
            $rules['owner_id'] = ['bail', 'required', 'integer', Rule::exists('users', 'id')->where(
                fn (Builder $query) => $query->where('role', Role::Agent->value)->where('is_active', true),
            )];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = __('clients.attributes');
        $names = [];

        foreach (is_array($attributes) ? $attributes : [] as $field => $name) {
            if (is_string($field) && is_string($name)) {
                $names[$field] = $name;
            }
        }

        return $names;
    }

    public function clientData(): ClientData
    {
        $sector = $this->sector();
        $freeText = $this->string('sector_free_text')->squish()->toString();
        $contact = $this->string('contact_name')->squish()->toString();

        return new ClientData(
            companyName: $this->string('company_name')->squish()->toString(),
            vatNumber: VatNumber::clean($this->string('vat_number')->toString()),
            email: $this->string('email')->toString(),
            sectorId: $this->integer('commodity_sector_id'),
            sectorFreeText: $sector?->requires_free_text === true && $freeText !== '' ? $freeText : null,
            contactName: $contact !== '' ? $contact : null,
        );
    }

    public function logo(): ?UploadedFile
    {
        $logo = $this->file('logo');

        return $logo instanceof UploadedFile ? $logo : null;
    }

    /**
     * Owner of a new client: the chosen agent for the admin (D-358), the agent himself otherwise.
     */
    public function owner(): User
    {
        $actor = $this->actor();

        return $actor->role === Role::Admin
            ? User::query()->findOrFail($this->integer('owner_id'))
            : $actor;
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    private function client(): ?Client
    {
        $client = $this->route('client');

        return $client instanceof Client ? $client : null;
    }

    private function sector(): ?CommoditySector
    {
        $id = $this->input('commodity_sector_id');

        return is_numeric($id) ? CommoditySector::query()->find((int) $id) : null;
    }
}
