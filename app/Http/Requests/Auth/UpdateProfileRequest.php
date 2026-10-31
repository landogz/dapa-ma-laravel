<?php

namespace App\Http\Requests\Auth;

use App\Support\InterestValues;
use App\Support\Username\Username;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
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
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:240'],
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'nickname' => Username::rules($this->user()?->id),
            'profile_photo' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
            'about' => ['sometimes', 'nullable', 'string', 'max:70'],
            'region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'journal_custom_feelings' => ['sometimes', 'nullable', 'array', 'max:40'],
            'journal_custom_feelings.*' => ['string', 'max:80'],
            'interests' => ['sometimes', 'nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'max:80'],
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
