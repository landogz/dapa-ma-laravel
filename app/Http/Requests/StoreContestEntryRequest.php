<?php

namespace App\Http\Requests;

use App\Models\Contest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContestEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contest = $this->resolveContest();
        $category = $contest?->category;

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'creator_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'region' => ['nullable', 'string', 'max:255'],
        ];

        return match ($category) {
            'song' => [
                ...$rules,
                'entry_type' => ['required', Rule::in(['song', 'playlist'])],
                'lyrics' => ['nullable', 'string', 'max:20000'],
                'media_url' => ['nullable', 'url', 'max:512'],
                'cover_image_url' => ['nullable', 'url', 'max:512'],
            ],
            'poster' => [
                ...$rules,
                'poster_image_url' => ['nullable', 'url', 'max:1024'],
                'media_url' => ['nullable', 'url', 'max:1024'],
                'poster_image' => ['nullable', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif'],
            ],
            'video' => [
                ...$rules,
                'video_url' => ['required', 'url', 'max:1024'],
                'thumbnail_url' => ['nullable', 'url', 'max:1024'],
            ],
            default => $rules,
        };
    }

    public function withValidator($validator): void
    {
        $contest = $this->resolveContest();

        $validator->after(function ($validator) use ($contest): void {
            if (! $contest) {
                return;
            }

            if ($contest->category === 'song') {
                if (! $this->filled('media_url') && ! $this->filled('lyrics')) {
                    $validator->errors()->add(
                        'media_url',
                        'Provide a media URL or lyrics for this song entry.',
                    );
                }
            }

            if ($contest->category === 'poster') {
                if (
                    ! $this->filled('poster_image_url')
                    && ! $this->filled('media_url')
                    && ! $this->hasFile('poster_image')
                ) {
                    $validator->errors()->add(
                        'poster_image',
                        'Upload a poster image or provide a poster image URL.',
                    );
                }
            }
        });
    }

    private function resolveContest(): ?Contest
    {
        $contest = $this->route('contest');

        if ($contest instanceof Contest) {
            return $contest;
        }

        if (is_numeric($contest)) {
            return Contest::query()->find((int) $contest);
        }

        return null;
    }
}
