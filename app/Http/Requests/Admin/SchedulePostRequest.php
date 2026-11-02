<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SchedulePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'publish_date' => ['required', 'date', 'after_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'publish_date.after_or_equal' => 'Pick a date and time that is now or in the future.',
        ];
    }
}
