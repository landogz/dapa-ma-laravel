<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoContestEntryRequest extends FormRequest
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
            'video_url' => ['required', 'url', 'max:1024'],
            'thumbnail_url' => ['nullable', 'url', 'max:1024'],
            'region' => ['nullable', 'string', 'max:255'],
        ];
    }
}
