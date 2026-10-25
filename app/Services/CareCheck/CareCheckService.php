<?php

namespace App\Services\CareCheck;

use App\Models\CareCheckResult;
use App\Models\CareToolkitQuestion;
use App\Models\User;
use App\Repositories\CareCheck\CareCheckRepository;
use App\Repositories\CareToolkitQuestionRepository;
use App\Support\CareCheck\Scoring;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class CareCheckService
{
    public function __construct(
        private readonly CareCheckRepository $careCheckRepository,
        private readonly CareToolkitQuestionRepository $questionRepository,
    ) {
    }

    public function listForUser(User $user, int $perPage, ?string $checkType = null): LengthAwarePaginator
    {
        return $this->careCheckRepository->paginateForUser($user, $perPage, $checkType);
    }

    public function latestForUser(User $user, string $checkType): ?CareCheckResult
    {
        return $this->careCheckRepository->latestForUser($user, $checkType);
    }

    public function store(User $user, string $checkType, array $answers): CareCheckResult
    {
        $questions = $this->questionRepository->activeByType($checkType);
        if ($questions->isEmpty()) {
            throw new InvalidArgumentException('No questions available for this check.');
        }

        $scored = Scoring::compute($checkType, $questions, $answers);

        return $this->careCheckRepository->create($user, [
            'check_type' => $checkType,
            'raw_score' => $scored['raw_score'],
            'max_score' => $scored['max_score'],
            'percent' => $scored['percent'],
            'band' => $scored['band'],
            'display_tier' => $scored['display_tier'],
            'sleep_hours' => $scored['sleep_hours'],
            'answers' => $scored['answers'],
        ]);
    }

    public function listAdmin(int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->careCheckRepository->paginateAdmin($perPage, $filters);
    }

    public function showAdmin(int $id): CareCheckResult
    {
        return $this->careCheckRepository->findAdmin($id);
    }

    public function summary(int $days): array
    {
        $payload = $this->careCheckRepository->summary($days);
        $payload['type_counts'] = $this->careCheckRepository->typeCounts();
        $payload['tier_counts'] = $this->careCheckRepository->tierCounts();

        return $payload;
    }

    public function format(CareCheckResult $result, bool $includeAnswers = false): array
    {
        $data = [
            'id' => $result->id,
            'check_type' => $result->check_type,
            'raw_score' => $result->raw_score,
            'max_score' => $result->max_score,
            'percent' => $result->percent,
            'band' => $result->band,
            'display_tier' => $result->display_tier,
            'sleep_hours' => $result->sleep_hours,
            'created_at' => $result->created_at?->toIso8601String(),
        ];

        if ($includeAnswers) {
            $data['answers'] = $result->answers ?? [];
        }

        return $data;
    }

    public function formatAdmin(CareCheckResult $result): array
    {
        $data = $this->format($result, true);
        $data['user'] = $result->user ? [
            'id' => $result->user->id,
            'name' => $result->user->name,
            'email' => $result->user->email,
        ] : null;
        $data['level_label'] = $this->levelLabel($result->check_type);
        $data['tier_label'] = $this->tierLabel($result->check_type, $result->display_tier);

        return $data;
    }

    public function formatAdminDetail(CareCheckResult $result): array
    {
        $data = $this->formatAdmin($result);
        $questions = $this->questionsByIds(
            collect($result->answers ?? [])->pluck('question_id')->filter()->all(),
        );

        $data['answers'] = collect($result->answers ?? [])->map(function ($answer) use ($questions) {
            $id = (int) ($answer['question_id'] ?? 0);
            $question = $questions[$id] ?? null;
            $options = $question
                ? ($question->options_en ?: CareToolkitQuestion::defaultOptions($question->answer_type, 'en'))
                : [];
            $value = $answer['value'] ?? null;
            $label = $value;
            if ($question?->answer_type !== 'time' && is_numeric($value) && isset($options[(int) $value])) {
                $label = $options[(int) $value];
            }

            return [
                'question_id' => $id,
                'question' => $question?->question_en ?? ('Question '.$id),
                'answer_type' => $question?->answer_type,
                'value' => $value,
                'label' => is_string($label) || is_numeric($label) ? (string) $label : '',
                'score' => isset($answer['score']) ? (int) $answer['score'] : null,
            ];
        })->values()->all();

        return $data;
    }

    public function levelLabel(string $checkType): string
    {
        return match ($checkType) {
            'stress' => 'Your Stress Level',
            'anxiety' => 'Your Anxiety Level',
            default => 'Your Sleep Quality',
        };
    }

    public function tierLabel(string $checkType, string $displayTier): string
    {
        $high = $displayTier === 'high';

        return match ($checkType) {
            'stress' => $high ? 'Moderate to High' : 'Low to Moderate',
            'anxiety' => $high ? 'Moderate to Severe' : 'Minimal to Low',
            default => $high ? 'Needs Improvement' : 'Good',
        };
    }

    /**
     * @param  array<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, CareToolkitQuestion>
     */
    private function questionsByIds(array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        return CareToolkitQuestion::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
