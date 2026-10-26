<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
        $rules = [
            'title'            => ['sometimes', 'string', 'max:255'],
            'body'             => ['sometimes', 'string'],
            'category_id'      => ['sometimes', 'integer', 'exists:categories,id'],
            'media_file'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'youtube_url'      => ['nullable', 'url', 'max:2048'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'is_featured'      => ['sometimes', 'boolean'],
        ];

        if ($this->user()?->role === 'super_admin') {
            $rules['status'] = ['sometimes', 'string', 'in:draft,pending_review,scheduled,published,archived'];
            $rules['publish_date'] = ['required_if:status,scheduled', 'nullable', 'date'];
        }

        return $rules;
    }
}
