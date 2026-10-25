<?php

namespace App\Support\CareCheck;

use App\Models\CareToolkitQuestion;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class Scoring
{
    public const TIER_LOW = 'low';

    public const TIER_HIGH = 'high';

    /**
     * @param  Collection<int, CareToolkitQuestion>  $questions
     * @param  array<int, array{question_id:int, value:mixed}>  $answers
     * @return array{
     *     raw_score:int,
     *     max_score:int,
     *     percent:int,
     *     band:string,
     *     display_tier:string,
     *     sleep_hours:?float,
     *     answers:array<int, array{question_id:int, value:mixed, score:int}>
     * }
     */
    public static function compute(string $checkType, Collection $questions, array $answers): array
    {
        $byId = $questions->keyBy('id');
        $answerMap = [];
        foreach ($answers as $row) {
            $id = (int) ($row['question_id'] ?? 0);
            if ($id > 0) {
                $answerMap[$id] = $row['value'] ?? null;
            }
        }

        foreach ($questions as $question) {
            if (! array_key_exists($question->id, $answerMap) || $answerMap[$question->id] === null || $answerMap[$question->id] === '') {
                throw new InvalidArgumentException('Please answer all questions.');
            }
        }

        $normalized = [];
        $raw = 0;
        $max = 0;
        $sleepHours = null;

        $timeQuestions = $questions
            ->filter(fn (CareToolkitQuestion $q) => $q->answer_type === 'time')
            ->sortBy('sort_order')
            ->values();

        if ($checkType === 'sleep' && $timeQuestions->count() >= 2) {
            $bed = self::parseMinutes($answerMap[$timeQuestions[0]->id] ?? null);
            $wake = self::parseMinutes($answerMap[$timeQuestions[1]->id] ?? null);
            $sleepHours = round(self::durationHours($bed, $wake), 2);
            $durationScore = self::durationPenalty($sleepHours);
            $raw += $durationScore;
            $max += 4;
            $normalized[] = [
                'question_id' => (int) $timeQuestions[0]->id,
                'value' => $answerMap[$timeQuestions[0]->id],
                'score' => $durationScore,
            ];
            $normalized[] = [
                'question_id' => (int) $timeQuestions[1]->id,
                'value' => $answerMap[$timeQuestions[1]->id],
                'score' => $durationScore,
            ];
        } else {
            foreach ($timeQuestions as $question) {
                $minutes = self::parseMinutes($answerMap[$question->id] ?? null);
                $score = (int) round($minutes / 360);
                $score = max(0, min(4, $score));
                $raw += $score;
                $max += 4;
                $normalized[] = [
                    'question_id' => (int) $question->id,
                    'value' => $answerMap[$question->id],
                    'score' => $score,
                ];
            }
        }

        $likerts = $questions->filter(fn (CareToolkitQuestion $q) => $q->answer_type !== 'time');
        foreach ($likerts as $question) {
            $options = is_array($question->options_en) && count($question->options_en) > 0
                ? $question->options_en
                : CareToolkitQuestion::defaultOptions($question->answer_type, 'en');
            $top = max(1, count($options) - 1);
            $value = (int) $answerMap[$question->id];
            $value = max(0, min($top, $value));
            $score = $question->is_reverse ? ($top - $value) : $value;
            $raw += $score;
            $max += $top;
            $normalized[] = [
                'question_id' => (int) $question->id,
                'value' => $value,
                'score' => $score,
            ];
        }

        $max = max(1, $max);
        $percent = (int) round(($raw / $max) * 100);
        $percent = max(0, min(100, $percent));
        $band = self::band($checkType, $raw, $percent);
        $tier = self::displayTier($checkType, $raw, $percent);

        return [
            'raw_score' => $raw,
            'max_score' => $max,
            'percent' => $percent,
            'band' => $band,
            'display_tier' => $tier,
            'sleep_hours' => $sleepHours,
            'answers' => $normalized,
        ];
    }

    public static function displayTier(string $checkType, int $raw, int $percent): string
    {
        return match ($checkType) {
            'stress' => $raw <= 13 ? self::TIER_LOW : self::TIER_HIGH,
            'anxiety' => $raw <= 9 ? self::TIER_LOW : self::TIER_HIGH,
            default => $percent <= 40 ? self::TIER_LOW : self::TIER_HIGH,
        };
    }

    public static function band(string $checkType, int $raw, int $percent): string
    {
        return match ($checkType) {
            'stress' => $raw <= 7 ? 'low' : ($raw <= 13 ? 'moderate' : 'high'),
            'anxiety' => match (true) {
                $raw <= 4 => 'minimal',
                $raw <= 9 => 'mild',
                $raw <= 14 => 'moderate',
                default => 'severe',
            },
            default => $percent <= 40 ? 'good' : ($percent <= 65 ? 'fair' : 'poor'),
        };
    }

    public static function parseMinutes(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(0, min(1439, (int) $value));
        }

        $text = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $text, $m) !== 1) {
            throw new InvalidArgumentException('Enter a valid time.');
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException('Enter a valid time.');
        }

        return ($hour * 60) + $minute;
    }

    public static function durationHours(int $bedMinutes, int $wakeMinutes): float
    {
        $span = $wakeMinutes - $bedMinutes;
        if ($span <= 0) {
            $span += 24 * 60;
        }

        return $span / 60;
    }

    public static function durationPenalty(float $hours): int
    {
        if ($hours >= 7 && $hours <= 9) {
            return 0;
        }
        if (($hours >= 6 && $hours < 7) || ($hours > 9 && $hours <= 10)) {
            return 1;
        }
        if (($hours >= 5 && $hours < 6) || ($hours > 10 && $hours <= 11)) {
            return 2;
        }
        if (($hours >= 4 && $hours < 5) || ($hours > 11 && $hours <= 12)) {
            return 3;
        }

        return 4;
    }
}
