<?php

namespace App\Http\Requests\Admin;

use App\Support\Password\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicy::rules(),
            'role' => [
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

