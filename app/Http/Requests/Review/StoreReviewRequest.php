<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => [
                'required',
                Rule::in(['post', 'rehab_center']),
            ],
            'target_id' => ['required', 'integer'],
            'rating'    => ['required', 'integer', 'min:1', 'max:5'],
            'comment'   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
