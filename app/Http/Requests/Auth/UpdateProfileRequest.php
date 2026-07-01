<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'profile_photo' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
        ];
    }
}
