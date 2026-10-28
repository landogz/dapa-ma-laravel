<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Password\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = User::query()
            ->where('email', strtolower(trim((string) $this->input('email', ''))))
            ->first();

        return [
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => PasswordPolicy::rules(
                required: true,
                confirmed: true,
                user: $user,
            ),
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => PasswordPolicy::hint(),
        ];
    }
}
