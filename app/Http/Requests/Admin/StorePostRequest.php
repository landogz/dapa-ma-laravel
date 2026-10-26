<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['comments_enabled', 'is_featured'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'body'              => ['required', 'string'],
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'media_file'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'youtube_url'       => ['nullable', 'url', 'max:2048'],
            'comments_enabled'  => ['sometimes', 'boolean'],
            'is_featured'       => ['sometimes', 'boolean'],
        ];
    }
}
