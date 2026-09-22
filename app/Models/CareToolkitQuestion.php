<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareToolkitQuestion extends Model
{
    public const TYPES = ['stress', 'anxiety', 'sleep'];

    public const ANSWER_TYPES = ['likert5', 'likert4', 'time'];

    protected $fillable = [
        'toolkit_type',
        'answer_type',
        'question_en',
        'question_tl',
        'options_en',
        'options_tl',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options_en' => 'array',
            'options_tl' => 'array',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function toPublicArray(string $locale = 'en'): array
    {
        $isTl = in_array($locale, ['tl', 'fil'], true);
        $options = $isTl
            ? ($this->options_tl ?: $this->options_en)
            : ($this->options_en ?: $this->options_tl);

        if (empty($options)) {
            $options = self::defaultOptions($this->answer_type, $isTl ? 'tl' : 'en');
        }

        return [
            'id'          => $this->id,
            'toolkit_type'=> $this->toolkit_type,
            'answer_type' => $this->answer_type,
            'question'    => $isTl ? $this->question_tl : $this->question_en,
            'options'     => array_values($options ?? []),
            'sort_order'  => $this->sort_order,
        ];
    }

    public function toAdminArray(): array
    {
        return [
            'id'           => $this->id,
            'toolkit_type' => $this->toolkit_type,
            'answer_type'  => $this->answer_type,
            'question_en'  => $this->question_en,
            'question_tl'  => $this->question_tl,
            'options_en'   => $this->options_en,
            'options_tl'   => $this->options_tl,
            'sort_order'   => $this->sort_order,
            'is_active'    => $this->is_active,
            'created_at'   => optional($this->created_at)?->toIso8601String(),
            'updated_at'   => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    public static function defaultOptions(string $answerType, string $locale = 'en'): array
    {
        $isTl = $locale === 'tl';

        return match ($answerType) {
            'likert4' => $isTl
                ? ['Hindi talaga', 'Ilang araw', 'Mahigit kalahati ng mga araw', 'Halos araw-araw']
                : ['Not at all', 'Several days', 'More than half the days', 'Nearly every day'],
            'time' => [],
            default => $isTl
                ? ['Hindi kailanman', 'Halos hindi', 'Minsan', 'Medyo madalas', 'Napakadalas']
                : ['Never', 'Almost Never', 'Sometimes', 'Fairly Often', 'Very Often'],
        };
    }
}
