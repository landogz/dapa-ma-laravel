<?php

namespace App\Http\Requests\HopeEvent;

use App\Models\HopeEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHopeEventRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'audience'         => ['nullable', 'string', Rule::in(HopeEvent::AUDIENCES)],
            'status'           => ['nullable', 'string', Rule::in(HopeEvent::STATUSES)],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'venue'            => ['nullable', 'string', 'max:255'],
            'is_online'        => ['nullable', 'boolean'],
            'online_label'     => ['nullable', 'string', 'max:255'],
            'slots'            => ['nullable', 'integer', 'min:0'],
            'registration_url' => ['nullable', 'url', 'max:500'],
            'about_text'       => ['nullable', 'string', 'max:20000'],
            'for_you_items'    => ['nullable', 'array', 'max:20'],
            'for_you_items.*'  => ['string', 'max:255'],
            'who_can_join'     => ['nullable', 'string', 'max:5000'],
            'highlights'       => ['nullable', 'array', 'max:20'],
            'details_text'     => ['nullable', 'string', 'max:20000'],
            'speakers'         => ['nullable', 'array', 'max:30'],
            'speakers.*.name'  => ['required_with:speakers', 'string', 'max:255'],
            'speakers.*.role'  => ['nullable', 'string', 'max:255'],
            'faqs'             => ['nullable', 'array', 'max:30'],
            'faqs.*.question'  => ['required_with:faqs', 'string', 'max:500'],
            'faqs.*.answer'    => ['required_with:faqs', 'string', 'max:5000'],
            'cover'            => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'        => ['nullable', 'boolean'],
        ];
    }
}
