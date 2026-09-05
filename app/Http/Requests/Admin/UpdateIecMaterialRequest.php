<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIecMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        if ($this->filled('sort_order')) {
            $this->merge([
                'sort_order' => (int) $this->input('sort_order'),
            ]);
        }

        foreach (['topic', 'description', 'media_url', 'thumbnail_url'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'topic' => ['nullable', 'string', 'max:100'],
            'media_type' => ['sometimes', 'required', Rule::in(['gif', 'image', 'youtube', 'lottie'])],
            'media_url' => ['nullable', 'url', 'max:1024'],
            'thumbnail_url' => ['nullable', 'url', 'max:1024'],
            'media_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'thumbnail_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mediaType = $this->input('media_type');
            $hasUrl = filled($this->input('media_url'));
            $hasFile = $this->hasFile('media_file');
            $hasExistingMedia = filled($this->route('iecMaterial')?->media_url);

            if (in_array($mediaType, ['youtube', 'lottie'], true)) {
                if (! $hasUrl && ! $hasExistingMedia) {
                    $validator->errors()->add('media_url', 'A media URL is required for YouTube/Lottie materials.');
                }

                return;
            }

            if (! $hasUrl && ! $hasFile && ! $hasExistingMedia) {
                $validator->errors()->add('media_url', 'Upload a file or paste a media URL.');
            }
        });
    }
}
