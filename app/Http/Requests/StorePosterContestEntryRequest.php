<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePosterContestEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'creator_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'poster_image_url' => ['nullable', 'url', 'max:1024'],
            'poster_image' => ['nullable', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif'],
            'region' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('poster_image_url') && ! $this->hasFile('poster_image')) {
                $validator->errors()->add(
                    'poster_image',
                    'Upload a poster image or provide a poster image URL.',
                );
            }
        });
    }
}
