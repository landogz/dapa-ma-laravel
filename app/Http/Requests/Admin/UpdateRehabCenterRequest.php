<?php

namespace App\Http\Requests\Admin;

use App\Models\AppOption;
use App\Support\AppOptionGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRehabCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:64'],
            'region'   => ['sometimes', 'string', 'max:255'],
            'province' => ['sometimes', 'string', 'max:255'],
            'address'  => ['sometimes', 'string', 'max:512'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'contact'  => ['nullable', 'string', 'max:100'],
            'website'  => ['nullable', 'url', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('category')) {
                return;
            }

            $category = $this->input('category');
            if (! is_string($category) || $category === '') {
                $validator->errors()->add('category', 'Category is required.');

                return;
            }

            if (! AppOption::isAllowed(AppOptionGroup::REHAB_CENTER, $category)) {
                $validator->errors()->add('category', 'Select a valid rehab center category from Lookups.');
            }
        });
    }
}
