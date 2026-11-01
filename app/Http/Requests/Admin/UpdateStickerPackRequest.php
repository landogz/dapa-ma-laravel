<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStickerPackRequest extends FormRequest
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
            $this->merge(['sort_order' => (int) $this->input('sort_order')]);
        }

        if ($this->has('remove_cover')) {
            $this->merge([
                'remove_cover' => filter_var($this->input('remove_cover'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->exists('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }

        if ($this->exists('slug') && $this->input('slug') === '') {
            $this->merge(['slug' => null]);
        }
    }

    public function rules(): array
    {
        $packId = $this->route('stickerPack')?->id ?? $this->route('sticker_pack');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('sticker_packs', 'slug')->ignore($packId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
