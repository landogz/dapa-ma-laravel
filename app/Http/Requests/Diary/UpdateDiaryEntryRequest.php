<?php

namespace App\Http\Requests\Diary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDiaryEntryRequest extends FormRequest
{
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
            'title'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'sky'        => ['sometimes', 'nullable', 'string', Rule::in(StoreDiaryEntryRequest::SKY_VALUES)],
            'feelings'   => ['sometimes', 'nullable', 'array', 'max:20'],
            'feelings.*' => ['string', 'max:80'],
            'impact'     => ['sometimes', 'nullable', 'string', Rule::in(StoreDiaryEntryRequest::IMPACT_VALUES)],
            'gratitude'    => ['sometimes', 'nullable', 'string', 'max:5000'],
            'body_html'    => ['sometimes', 'nullable', 'string', 'max:50000'],
            'image'        => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->hasAny(['sky', 'feelings', 'impact', 'gratitude', 'body_html', 'title', 'image', 'remove_image'])) {
                return;
            }

            $sky = $this->exists('sky') ? $this->input('sky') : '__keep__';
            $feelings = $this->exists('feelings') ? $this->input('feelings') : '__keep__';
            $impact = $this->exists('impact') ? $this->input('impact') : '__keep__';
            $gratitude = $this->exists('gratitude') ? trim((string) $this->input('gratitude', '')) : '__keep__';
            $body = $this->exists('body_html')
                ? trim(strip_tags((string) $this->input('body_html', '')))
                : '__keep__';
            $hasImageUpload = $this->hasFile('image');
            $removingImage = $this->boolean('remove_image');

            // Only enforce "at least one field" when the client sends a full reflection payload.
            if ($sky === '__keep__' || $feelings === '__keep__' || $impact === '__keep__'
                || $gratitude === '__keep__' || $body === '__keep__') {
                return;
            }

            $hasFeelings = is_array($feelings)
                && count(array_filter($feelings, fn ($f) => trim((string) $f) !== '')) > 0;

            if (!$sky && !$hasFeelings && !$impact && $gratitude === '' && $body === ''
                && !$hasImageUpload && $removingImage) {
                $validator->errors()->add(
                    'body_html',
                    'Add at least one reflection field (sky, feelings, impact, notes, gratitude, or image).'
                );
            }
        });
    }
}
