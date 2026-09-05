<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIecMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'topic' => ['nullable', 'string', 'max:100'],
            'media_type' => ['sometimes', 'required', Rule::in(['gif', 'image', 'youtube', 'lottie'])],
            'media_url' => ['sometimes', 'required', 'url', 'max:1024'],
            'thumbnail_url' => ['nullable', 'url', 'max:1024'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
