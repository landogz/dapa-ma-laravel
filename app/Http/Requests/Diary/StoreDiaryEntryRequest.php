<?php

namespace App\Http\Requests\Diary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiaryEntryRequest extends FormRequest
{
    public const SKY_VALUES = [
        'clear_skies',
        'passing_mist',
        'overcast',
        'stormy',
    ];

    public const IMPACT_VALUES = [
        'connection_circle',
        'cravings_urges',
        'routine_sleep',
        'environment_triggers',
        'self_care_reflection',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $feelings = $this->input('feelings');
        if (is_string($feelings)) {
            $decoded = json_decode($feelings, true);
            if (is_array($decoded)) {
                $this->merge(['feelings' => $decoded]);
            }
        }

        if ($this->has('remove_image')) {
            $this->merge([
                'remove_image' => filter_var(
                    $this->input('remove_image'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ) ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'entry_date'   => ['required', 'date'],
            'title'        => ['nullable', 'string', 'max:255'],
            'sky'          => ['nullable', 'string', Rule::in(self::SKY_VALUES)],
            'feelings'     => ['nullable', 'array', 'max:20'],
            'feelings.*'   => ['string', 'max:80'],
            'impact'       => ['nullable', 'string', Rule::in(self::IMPACT_VALUES)],
            'gratitude'    => ['nullable', 'string', 'max:5000'],
            'body_html'    => ['nullable', 'string', 'max:50000'],
            'image'        => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sky = $this->input('sky');
            $feelings = $this->input('feelings');
            $impact = $this->input('impact');
            $gratitude = trim((string) $this->input('gratitude', ''));
            $body = trim(strip_tags((string) $this->input('body_html', '')));
            $hasImage = $this->hasFile('image');

            $hasFeelings = is_array($feelings) && count(array_filter($feelings, fn ($f) => trim((string) $f) !== '')) > 0;

            if (!$sky && !$hasFeelings && !$impact && $gratitude === '' && $body === '' && !$hasImage) {
                $validator->errors()->add(
                    'body_html',
                    'Add at least one reflection field (sky, feelings, impact, notes, gratitude, or image).'
                );
            }
        });
    }
}
