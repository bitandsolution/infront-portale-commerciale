<?php

namespace App\Http\Requests\Clients;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reassignment of a client (M05 F-07; D-082): the new owner is an active agent (D-358). That he is
 * not already the owner, and that the client is not merged, is checked by ReassignClient.
 */
class ReassignClientRequest extends FormRequest
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
            'owner_id' => ['bail', 'required', 'integer', Rule::exists('users', 'id')->where(
                fn (Builder $query) => $query->where('role', Role::Agent->value)->where('is_active', true),
            )],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['owner_id' => (string) __('clients.attributes.owner_id')];
    }

    public function owner(): User
    {
        return User::query()->findOrFail($this->integer('owner_id'));
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
