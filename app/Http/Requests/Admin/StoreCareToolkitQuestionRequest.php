<?php

namespace App\Http\Requests\Admin;

use App\Models\CareToolkitQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareToolkitQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'toolkit_type' => ['required', 'string', Rule::in(CareToolkitQuestion::TYPES)],
            'answer_type'  => ['required', 'string', Rule::in(CareToolkitQuestion::ANSWER_TYPES)],
            'question_en'  => ['required', 'string', 'max:2000'],
            'question_tl'  => ['required', 'string', 'max:2000'],
            'options_en'   => ['nullable'],
            'options_tl'   => ['nullable'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
