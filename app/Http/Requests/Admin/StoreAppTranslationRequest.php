<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppTranslationRequest extends FormRequest
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

        foreach (['group', 'description'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z][A-Za-z0-9_.]*$/', Rule::unique('app_translations', 'key')],
            'group' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            'value_en' => ['required', 'string', 'max:10000'],
            'value_tl' => ['required', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
