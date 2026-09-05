<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSongContestEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'artist_name' => ['required', 'string', 'max:255'],
            'entry_type' => ['required', Rule::in(['song', 'playlist'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'lyrics' => ['nullable', 'string', 'max:20000'],
            'media_url' => ['nullable', 'url', 'max:512'],
            'cover_image_url' => ['nullable', 'url', 'max:512'],
            'region' => ['nullable', 'string', 'max:255'],
        ];
    }
}
