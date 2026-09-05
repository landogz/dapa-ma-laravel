<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:512'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'schedule_notes' => ['nullable', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:100'],
            'registration_url' => ['nullable', 'url', 'max:512'],
            'slots' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
