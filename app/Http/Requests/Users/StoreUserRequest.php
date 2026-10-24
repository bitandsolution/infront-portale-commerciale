<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Actions\CreateUser;
use App\Domain\Users\Data\NewUserData;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New user from the Users page (M01 §5, D-139): name, surname, e-mail (unique), role, optional
 * phone. No password: the user sets it from the activation link (D-278).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...CreateUser::rules(withPassword: false),
            'role' => ['required', 'string', Rule::enum(Role::class)],
        ];
    }

    public function data(): NewUserData
    {
        return new NewUserData(
            firstName: $this->string('first_name')->toString(),
            lastName: $this->string('last_name')->toString(),
            email: $this->string('email')->toString(),
            role: Role::from($this->string('role')->toString()),
            phone: $this->string('phone')->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }
}
