<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
}
