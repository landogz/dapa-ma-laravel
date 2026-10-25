<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CheckEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = strtolower(trim((string) $this->input('email', '')));
        $this->merge(['email' => $email]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255', 'email:filter'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email address before continuing.',
        ];
    }
}
