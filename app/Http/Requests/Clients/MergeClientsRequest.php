<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Merge of a duplicate client (M05 F-08; D-359): keep_id is the client that stays. The rules on the
 * two clients (not the same, the one that stays active and not merged) are checked by MergeClients
 * under the row locks.
 */
class MergeClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client && ($this->user()?->can('manage', $client) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'keep_id' => ['bail', 'required', 'integer', Rule::exists('clients', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['keep_id' => (string) __('clients.attributes.keep_id')];
    }

    public function keep(): Client
    {
        return Client::query()->findOrFail($this->integer('keep_id'));
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
