<?php

namespace App\Http\Requests;

use App\Support\Password\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email', ''))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicy::rules(),
            'accepted_terms_of_use' => ['required', 'accepted'],
            'accepted_privacy_policy' => ['required', 'accepted'],
            'accepted_privacy_notice' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Enter a valid email address before continuing.',
            'email.unique' => 'This email is already registered.',
            'accepted_terms_of_use.accepted' => 'You must agree to the Terms of Use before continuing.',
            'accepted_privacy_policy.accepted' => 'You must agree to the Privacy Policy before continuing.',
            'accepted_privacy_notice.accepted' => 'You must accept the Privacy Notice before continuing.',
            'password.required' => PasswordPolicy::hint(),
        ];
    }
}
