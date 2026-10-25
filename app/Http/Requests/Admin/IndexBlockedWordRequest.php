<?php

namespace App\Http\Requests\Admin;

use App\Support\BooleanValue\Cast;
use Illuminate\Foundation\Http\FormRequest;

class IndexBlockedWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'search' => ['sometimes', 'nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('is_active')) {
            return;
        }

        $this->merge([
            'is_active' => Cast::from($this->input('is_active')),
        ]);
    }
}
