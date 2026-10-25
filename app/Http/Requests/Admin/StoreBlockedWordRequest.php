<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlockedWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'word' => ['required', 'string', 'min:2', 'max:80', 'unique:blocked_words,word'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('word')) {
            $this->merge([
                'word' => mb_strtolower(trim((string) $this->input('word'))),
            ]);
        }
    }
}
