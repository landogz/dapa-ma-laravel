<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', Rule::in(['song', 'poster', 'video'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'theme' => ['nullable', 'string', 'max:255'],
            'allowed_entry_types' => ['nullable', Rule::in(['song', 'playlist', 'both'])],
            'contest_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'submission_starts_at' => ['nullable', 'date'],
            'submission_ends_at' => ['nullable', 'date', 'after_or_equal:submission_starts_at'],
            'status' => ['sometimes', Rule::in(['draft', 'open', 'closed', 'completed'])],
            'cover_image_url' => ['nullable', 'url', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
