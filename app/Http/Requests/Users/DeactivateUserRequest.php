<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deactivation of a user (M01 §5), with the optional agent who receives all the clients of a
 * deactivated agent (M01-CA4, D-360). Who can receive them (another active agent) is checked by
 * DeactivateUser under the row locks.
 */
class DeactivateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deactivate', $this->target()) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'clients_owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['clients_owner_id' => (string) __('users.attributes.clients_owner_id')];
    }

    public function target(): User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : abort(404);
    }

    public function clientsOwner(): ?User
    {
        return $this->filled('clients_owner_id')
            ? User::query()->findOrFail($this->integer('clients_owner_id'))
            : null;
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
