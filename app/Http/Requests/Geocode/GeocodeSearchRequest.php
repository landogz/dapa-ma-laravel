<?php

namespace App\Http\Requests\Geocode;

use Illuminate\Foundation\Http\FormRequest;

class GeocodeSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:3', 'max:160'],
        ];
    }
}
