<?php

namespace App\Http\Requests\Auth;

use App\Support\InterestValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOnboardingRequest extends FormRequest
{
    public const PERSONA_VALUES = [
        'student',
        'parent',
        'teacher',
        'youth_leader',
        'health_worker',
        'concerned_citizen',
    ];

    /** @deprecated Use InterestValues::PRESET */
    public const INTEREST_VALUES = InterestValues::PRESET;

    public const PRONOUN_VALUES = [
        'she_her',
        'he_him',
        'they_them',
        'ze_hir',
        'prefer_not',
        'others',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname'  => ['sometimes', 'nullable', 'string', 'max:120'],
            'pronouns'  => ['sometimes', 'nullable', 'string', Rule::in(self::PRONOUN_VALUES)],
            'birthday'  => ['sometimes', 'nullable', 'date', 'before:today'],
            'persona'   => ['sometimes', 'nullable', 'string', Rule::in(self::PERSONA_VALUES)],
            'interests' => ['sometimes', 'nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'max:80'],
            'complete'  => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $interests = $this->input('interests');
            if (! is_array($interests)) {
                return;
            }

            foreach ($interests as $index => $interest) {
                if (! is_string($interest) || ! InterestValues::isValid($interest)) {
                    $validator->errors()->add(
                        "interests.$index",
                        'Each interest must be a supported option or a custom Other value.',
                    );
                }
            }
        });
    }
}
