<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Password\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'current_password' => ['required', 'string'],
            'password' => array_merge(
                PasswordPolicy::rules(
                    required: true,
                    confirmed: true,
                    user: $user instanceof User ? $user : null,
                ),
                ['different:current_password'],
            ),
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => PasswordPolicy::hint(),
            'password.different' => 'New password must be different from your current password.',
        ];
    }
}
