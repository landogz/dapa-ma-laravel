<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRehabCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'region'   => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'address'  => ['required', 'string', 'max:512'],
            'contact'  => ['nullable', 'string', 'max:100'],
            'website'  => ['nullable', 'url', 'max:512'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
