<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Page of an access link (D-278, D-279): new password, typed twice, and the code of the
 * authenticator app. The token is checked by the Action, in a transaction.
 */
class AccessLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'code' => ['required', 'string', 'max:20'],
        ];
    }
}
