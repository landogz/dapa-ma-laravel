<?php

namespace App\Http\Requests\Diary;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'body_html' => ['sometimes', 'required', 'string', 'max:50000'],
        ];
    }
}
