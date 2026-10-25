<?php

namespace App\Http\Requests\Admin;

use App\Support\BooleanValue\Cast;
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
