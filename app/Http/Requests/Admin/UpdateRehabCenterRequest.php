<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRehabCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'region'   => ['sometimes', 'string', 'max:255'],
            'province' => ['sometimes', 'string', 'max:255'],
            'address'  => ['sometimes', 'string', 'max:512'],
            'contact'  => ['nullable', 'string', 'max:100'],
            'website'  => ['nullable', 'url', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
