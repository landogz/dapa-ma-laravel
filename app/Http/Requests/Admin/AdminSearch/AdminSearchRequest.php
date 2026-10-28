<?php

namespace App\Http\Requests\Admin\AdminSearch;

use Illuminate\Foundation\Http\FormRequest;

class AdminSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }
}
