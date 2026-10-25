<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Password dimenticata?" (D-279; PR-08): only the e-mail. Its format is checked, never whether an
 * account exists (the answer is the same for every address).
 */
class PasswordForgottenRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
