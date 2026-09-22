<?php

namespace App\Http\Requests\HopeEvent;

use App\Models\HopeEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHopeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['for_you_items', 'highlights', 'speakers', 'faqs'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (is_array($decoded)) {
                    $this->merge([$field => $decoded]);
                }
            }
        }

        if ($this->has('remove_cover')) {
            $this->merge([
                'remove_cover' => filter_var(
                    $this->input('remove_cover'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ) ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'audience'         => ['sometimes', 'nullable', 'string', Rule::in(HopeEvent::AUDIENCES)],
            'status'           => ['sometimes', 'nullable', 'string', Rule::in(HopeEvent::STATUSES)],
            'start_date'       => ['sometimes', 'nullable', 'date'],
            'end_date'         => ['sometimes', 'nullable', 'date'],
            'venue'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_online'        => ['sometimes', 'boolean'],
            'online_label'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'slots'            => ['sometimes', 'nullable', 'integer', 'min:0'],
            'registration_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'about_text'       => ['sometimes', 'nullable', 'string', 'max:20000'],
            'for_you_items'    => ['sometimes', 'nullable', 'array', 'max:20'],
            'for_you_items.*'  => ['string', 'max:255'],
            'who_can_join'     => ['sometimes', 'nullable', 'string', 'max:5000'],
            'highlights'       => ['sometimes', 'nullable', 'array', 'max:20'],
            'details_text'     => ['sometimes', 'nullable', 'string', 'max:20000'],
            'speakers'         => ['sometimes', 'nullable', 'array', 'max:30'],
            'speakers.*.name'  => ['required_with:speakers', 'string', 'max:255'],
            'speakers.*.role'  => ['nullable', 'string', 'max:255'],
            'faqs'             => ['sometimes', 'nullable', 'array', 'max:30'],
            'faqs.*.question'  => ['required_with:faqs', 'string', 'max:500'],
            'faqs.*.answer'    => ['required_with:faqs', 'string', 'max:5000'],
            'cover'            => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_cover'     => ['sometimes', 'boolean'],
            'sort_order'       => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }
}
