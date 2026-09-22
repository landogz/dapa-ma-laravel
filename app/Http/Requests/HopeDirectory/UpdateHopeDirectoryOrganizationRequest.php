<?php

namespace App\Http\Requests\HopeDirectory;

use App\Models\HopeDirectoryOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHopeDirectoryOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('remove_logo')) {
            $this->merge([
                'remove_logo' => filter_var(
                    $this->input('remove_logo'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ) ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'category'     => ['sometimes', 'required', 'string', Rule::in(HopeDirectoryOrganization::CATEGORIES)],
            'address'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'email'        => ['sometimes', 'nullable', 'email', 'max:191'],
            'logo'         => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_logo'  => ['sometimes', 'boolean'],
            'sort_order'   => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
