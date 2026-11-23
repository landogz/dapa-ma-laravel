<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRelaxingMusicTrackRequest extends FormRequest
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

        if ($this->filled('duration_seconds')) {
            $this->merge(['duration_seconds' => (int) $this->input('duration_seconds')]);
        }

        if ($this->exists('title_tl') && $this->input('title_tl') === '') {
            $this->merge(['title_tl' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'title_tl' => ['nullable', 'string', 'max:255'],
            'audio' => ['nullable', 'file', 'mimes:mp3,m4a,aac,wav,ogg,mpeg', 'max:20480'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
