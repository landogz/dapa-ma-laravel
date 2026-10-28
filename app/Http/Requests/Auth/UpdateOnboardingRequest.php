<?php

namespace App\Http\Requests\Auth;

use App\Models\AppOption;
use App\Support\AppOptionGroup;
use App\Support\InterestValues;
use App\Support\Username\Username;
use Illuminate\Foundation\Http\FormRequest;
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

    protected function prepareForValidation(): void
    {
        if ($this->exists('nickname')) {
            $this->merge([
                'nickname' => Username::normalize($this->input('nickname')),
            ]);
        }

        $personas = $this->input('personas');
        if (is_string($personas)) {
            $decoded = json_decode($personas, true);
            if (is_array($decoded)) {
                $this->merge(['personas' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nickname' => Username::rules($this->user()?->id),
            'pronouns' => ['sometimes', 'nullable', 'string', 'max:80'],
            'birthday' => ['sometimes', 'nullable', 'date', 'before:today'],
            'persona' => ['sometimes', 'nullable', 'string', 'max:80'],
            'personas' => ['sometimes', 'nullable', 'array', 'max:20'],
            'personas.*' => ['string', 'max:80'],
            'interests' => ['sometimes', 'nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'max:80'],
            'complete' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.unique' => 'This username is already taken.',
            'nickname.regex' => 'Username may only contain letters, numbers, dots, underscores, and hyphens.',
            'nickname.min' => 'Username must be at least '.Username::MIN_LENGTH.' characters.',
            'nickname.max' => 'Username may not be greater than '.Username::MAX_LENGTH.' characters.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->exists('nickname')) {
                $nickname = Username::normalize($this->input('nickname'));
                if ($nickname !== null && Username::isTaken($nickname, $this->user()?->id)) {
                    $validator->errors()->add('nickname', 'This username is already taken.');
                }
            }

            if ($this->filled('pronouns') && ! AppOption::isAllowed(
                AppOptionGroup::PRONOUNS,
                (string) $this->input('pronouns'),
                self::PRONOUN_VALUES,
            )) {
                $validator->errors()->add('pronouns', 'The selected pronouns are invalid.');
            }

            $persona = $this->input('persona');
            if (is_string($persona) && $persona !== '' && ! AppOption::isAllowed(
                AppOptionGroup::PERSONA,
                $persona,
                self::PERSONA_VALUES,
            )) {
                $validator->errors()->add('persona', 'The selected description is invalid.');
            }

            $personas = $this->input('personas');
            if (is_array($personas)) {
                foreach ($personas as $index => $value) {
                    if (! is_string($value) || ! AppOption::isAllowed(
                        AppOptionGroup::PERSONA,
                        $value,
                        self::PERSONA_VALUES,
                    )) {
                        $validator->errors()->add(
                            "personas.$index",
                            'Each description must be a supported option.',
                        );
                    }
                }
            }

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
