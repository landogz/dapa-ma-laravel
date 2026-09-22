<?php

namespace App\Http\Requests\Auth;

use App\Support\InterestValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:240'],
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'profile_photo' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
            'interests' => ['sometimes', 'nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'max:80'],
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
