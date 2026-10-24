<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Data\UserDetailsData;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Data and role of a user, changed by the admin (M01 §5, D-139). The rules on the role (not his
 * own, not the last active admin) are checked by the Action, in a transaction.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->target()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->target()->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', Rule::enum(Role::class)],
        ];
    }

    public function data(): UserDetailsData
    {
        return new UserDetailsData(
            firstName: $this->string('first_name')->toString(),
            lastName: $this->string('last_name')->toString(),
            email: $this->string('email')->toString(),
            role: Role::from($this->string('role')->toString()),
            phone: $this->string('phone')->toString(),
        );
    }

    public function target(): User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : abort(404);
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }
}
