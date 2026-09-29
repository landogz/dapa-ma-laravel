<?php

namespace App\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFcmTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios', 'web'])],
        ];
    }
}
