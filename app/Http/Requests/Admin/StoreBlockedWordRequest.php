<?php

namespace App\Http\Requests\Admin;

use App\Support\BooleanValue\Cast;
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
        $payload = [];

        if ($this->has('word')) {
            $payload['word'] = mb_strtolower(trim((string) $this->input('word')));
        }

        if ($this->exists('is_active')) {
            $payload['is_active'] = Cast::from($this->input('is_active'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
