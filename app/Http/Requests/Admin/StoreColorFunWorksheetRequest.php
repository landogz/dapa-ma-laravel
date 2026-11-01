<?php

namespace App\Http\Requests\Admin;

use App\Models\ColorFunWorksheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColorFunWorksheetRequest extends FormRequest
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

        if ($this->filled('category')) {
            $this->merge(['category' => strtolower(trim((string) $this->input('category')))]);
        }

        if ($this->exists('title') && $this->input('title') === '') {
            $this->merge(['title' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(ColorFunWorksheet::worksheetCategories())],
            'image' => ['required_without:images', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'images' => ['required_without:image', 'nullable', 'array', 'min:1', 'max:40'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
