<?php

namespace App\Http\Requests\Mood;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoodCheckinRequest extends FormRequest
{
    public const MOOD_VALUES = [
        'struggling',
        'difficult',
        'meh',
        'good',
        'great',
    ];

    public const SOURCE_VALUES = [
        'care_hub',
        'mood_tracker',
        'stress',
        'anxiety',
        'sleep',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mood'   => ['required', 'string', Rule::in(self::MOOD_VALUES)],
            'source' => ['sometimes', 'string', Rule::in(self::SOURCE_VALUES)],
            'score'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'note'   => ['nullable', 'string', 'max:5000'],
        ];
    }
}
