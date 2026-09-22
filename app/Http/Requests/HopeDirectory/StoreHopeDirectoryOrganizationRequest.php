<?php

namespace App\Http\Requests\HopeDirectory;

use App\Models\HopeDirectoryOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHopeDirectoryOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category'    => ['required', 'string', Rule::in(HopeDirectoryOrganization::CATEGORIES)],
            'address'     => ['nullable', 'string', 'max:500'],
            'phone'       => ['nullable', 'string', 'max:64'],
            'email'       => ['nullable', 'email', 'max:191'],
            'logo'        => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
