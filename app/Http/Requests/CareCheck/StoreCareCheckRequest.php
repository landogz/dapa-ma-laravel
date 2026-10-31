<?php

namespace App\Http\Requests\CareCheck;

use App\Support\Care\CareToolkitType;
use Illuminate\Foundation\Http\FormRequest;

class StoreCareCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_type' => ['required', 'string', CareToolkitType::activeValidationRule()],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'min:1'],
            'answers.*.value' => ['required'],
        ];
    }
}
