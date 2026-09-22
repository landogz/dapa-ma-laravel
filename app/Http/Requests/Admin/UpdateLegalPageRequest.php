<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:255'],
            'title_tl' => ['required', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string', 'max:500'],
            'subtitle_tl' => ['nullable', 'string', 'max:500'],
            'intro_en' => ['nullable', 'string', 'max:2000'],
            'intro_tl' => ['nullable', 'string', 'max:2000'],
            'body_en' => ['required', 'string'],
            'body_tl' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
