<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Change of the password from the profile (M01 §5; D-214, D-273): current password, then the new
 * one twice, at least 12 characters and not in the breached-password archives (Password::defaults).
 */
class UpdatePasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
