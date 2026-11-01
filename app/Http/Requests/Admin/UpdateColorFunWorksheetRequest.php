<?php

namespace App\Http\Requests\Admin;

use App\Models\ColorFunWorksheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColorFunWorksheetRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', Rule::in(ColorFunWorksheet::worksheetCategories())],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
