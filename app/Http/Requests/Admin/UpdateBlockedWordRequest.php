<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlockedWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('blockedWord')?->id;

        return [
            'word' => ['required', 'string', 'min:2', 'max:80', Rule::unique('blocked_words', 'word')->ignore($id)],
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
