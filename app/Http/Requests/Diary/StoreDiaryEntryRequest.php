<?php

namespace App\Http\Requests\Diary;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'title'      => ['nullable', 'string', 'max:255'],
            'body_html'  => ['required', 'string', 'max:50000'],
        ];
    }
}
