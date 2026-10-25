<?php

namespace App\Http\Requests\CareCheck;

use App\Models\CareCheckResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_type' => ['required', 'string', Rule::in(CareCheckResult::TYPES)],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'min:1'],
            'answers.*.value' => ['required'],
        ];
    }
}
