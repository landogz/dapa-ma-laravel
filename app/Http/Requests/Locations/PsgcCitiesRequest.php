<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;

class PsgcCitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prv' => ['required', 'integer', 'min:1', 'max:9999'],
            'province_name' => ['nullable', 'string', 'max:160'],
        ];
    }
}
