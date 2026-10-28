<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Password\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = (int) $this->route('user')?->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => PasswordPolicy::rules(
                required: false,
                confirmed: true,
                user: $this->route('user') instanceof User ? $this->route('user') : null,
            ),
            'role' => [
                'sometimes',
                'required',
                Rule::in([
                    'super_admin',
                    'editor',
                    'publisher',
                    'analytics_viewer',
                    'app_user',
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => PasswordPolicy::hint(),
        ];
    }
}

