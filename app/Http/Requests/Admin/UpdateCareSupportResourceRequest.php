<?php

namespace App\Http\Requests\Admin;

use App\Models\CareSupportResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCareSupportResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'       => ['sometimes', 'string', Rule::in(CareSupportResource::CATEGORIES)],
            'title_en'       => ['sometimes', 'string', 'max:255'],
            'title_tl'       => ['sometimes', 'string', 'max:255'],
            'role_en'        => ['nullable', 'string', 'max:120'],
            'role_tl'        => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_tl' => ['nullable', 'string', 'max:5000'],
            'meta_en'        => ['nullable', 'string', 'max:255'],
            'meta_tl'        => ['nullable', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:120'],
            'web_url'        => ['nullable', 'string', 'max:500'],
            'logo_url'       => ['nullable', 'string', 'max:500'],
            'logo_initial'   => ['nullable', 'string', 'max:4'],
            'icon_key'       => ['nullable', 'string', 'max:32'],
            'body_en'        => ['nullable'],
            'body_tl'        => ['nullable'],
            'is_emergency'   => ['sometimes', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }
}
