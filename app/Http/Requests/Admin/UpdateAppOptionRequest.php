<?php

namespace App\Http\Requests\Admin;

use App\Support\AppOptionGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('is_active')) {
            $payload['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        foreach (['key', 'subtitle_en', 'subtitle_tl', 'icon', 'accent_color'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $payload[$field] = null;
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'group' => ['sometimes', 'string', Rule::in(AppOptionGroup::keys())],
            'key' => ['sometimes', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'label_en' => ['required', 'string', 'max:255'],
            'label_tl' => ['required', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string', 'max:500'],
            'subtitle_tl' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:80'],
            'accent_color' => ['nullable', 'string', 'max:16', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
