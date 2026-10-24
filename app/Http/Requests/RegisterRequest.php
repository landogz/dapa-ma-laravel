<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'accepted_terms_of_use' => ['required', 'accepted'],
            'accepted_privacy_policy' => ['required', 'accepted'],
            'accepted_privacy_notice' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'accepted_terms_of_use.accepted' => 'You must agree to the Terms of Use before continuing.',
            'accepted_privacy_policy.accepted' => 'You must agree to the Privacy Policy before continuing.',
            'accepted_privacy_notice.accepted' => 'You must accept the Privacy Notice before continuing.',
        ];
    }
}
