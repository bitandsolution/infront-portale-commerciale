<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Personal data of the signed-in user (M01 §5, D-267): name, surname, phone.
 * Every signed-in user may change his own: the route is behind the Profile Gate.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
