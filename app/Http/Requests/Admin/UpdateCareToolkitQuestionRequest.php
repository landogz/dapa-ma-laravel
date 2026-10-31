<?php

namespace App\Http\Requests\Admin;

use App\Models\CareToolkitQuestion;
use App\Support\Care\CareToolkitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCareToolkitQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'toolkit_type' => ['sometimes', 'string', CareToolkitType::validationRule()],
            'answer_type'  => ['sometimes', 'string', Rule::in(CareToolkitQuestion::ANSWER_TYPES)],
            'question_en'  => ['sometimes', 'string', 'max:2000'],
            'question_tl'  => ['sometimes', 'string', 'max:2000'],
            'options_en'   => ['nullable'],
            'options_tl'   => ['nullable'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'    => ['sometimes', 'boolean'],
            'is_reverse'   => ['sometimes', 'boolean'],
        ];
    }
}
