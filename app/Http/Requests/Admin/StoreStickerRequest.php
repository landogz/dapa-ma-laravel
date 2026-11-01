<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        }

        if ($this->filled('sort_order')) {
            $this->merge(['sort_order' => (int) $this->input('sort_order')]);
        }

        if ($this->filled('sticker_pack_id')) {
            $this->merge(['sticker_pack_id' => (int) $this->input('sticker_pack_id')]);
        }

        if ($this->exists('name') && $this->input('name') === '') {
            $this->merge(['name' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'sticker_pack_id' => ['required', 'integer', 'exists:sticker_packs,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
