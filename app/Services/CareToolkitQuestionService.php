<?php

namespace App\Services;

use App\Models\CareToolkitQuestion;
use App\Models\User;
use App\Repositories\CareToolkitQuestionRepository;
use App\Services\ContentWorkflow\ContentWorkflowService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CareToolkitQuestionService
{
    public function __construct(
        private readonly CareToolkitQuestionRepository $repository,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    public function list(
        int $perPage = 50,
        ?string $search = null,
        ?string $toolkitType = null,
        ?User $actor = null,
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $search, $toolkitType, $actor);
    }

    public function find(int $id): CareToolkitQuestion
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data, User $actor): CareToolkitQuestion
    {
        $payload = $this->contentWorkflow->prepareCreatePayload($actor, $this->normalize($data));

        return $this->repository->create($payload);
    }

    public function update(CareToolkitQuestion $question, array $data, User $actor): CareToolkitQuestion
    {
        $payload = $this->contentWorkflow->prepareUpdatePayload(
            $question,
            $actor,
            $this->normalize($data, $question),
        );

        return $this->repository->update($question, $payload);
    }

    public function delete(CareToolkitQuestion $question): void
    {
        $this->repository->delete($question);
    }

    public function publicByType(string $toolkitType, ?string $locale = null): array
    {
        $locale = $locale ?? 'en';

        return $this->repository
            ->activeByType($toolkitType)
            ->map(fn (CareToolkitQuestion $q) => $q->toPublicArray($locale))
            ->values()
            ->all();
    }

    private function normalize(array $data, ?CareToolkitQuestion $existing = null): array
    {
        $toolkitType = $data['toolkit_type'] ?? $existing?->toolkit_type ?? 'stress';

        $sortOrder = array_key_exists('sort_order', $data)
            ? (int) $data['sort_order']
            : ($existing?->sort_order ?? $this->repository->nextSortOrder($toolkitType));

        return [
            'toolkit_type' => $toolkitType,
            'answer_type'  => $data['answer_type'] ?? $existing?->answer_type ?? 'likert5',
            'question_en'  => trim((string) ($data['question_en'] ?? $existing?->question_en ?? '')),
            'question_tl'  => trim((string) ($data['question_tl'] ?? $existing?->question_tl ?? '')),
            'options_en'   => $this->normalizeOptions($data['options_en'] ?? $existing?->options_en),
            'options_tl'   => $this->normalizeOptions($data['options_tl'] ?? $existing?->options_tl),
            'sort_order'   => max(0, $sortOrder),
            'is_active'    => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($existing?->is_active ?? true),
            'is_reverse'   => array_key_exists('is_reverse', $data)
                ? (bool) $data['is_reverse']
                : ($existing?->is_reverse ?? false),
        ];
    }

    private function normalizeOptions(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

            return $lines === [] ? null : $lines;
        }

        if (is_array($value)) {
            $lines = array_values(array_filter(array_map(
                fn ($l) => trim((string) $l),
                $value,
            ), fn ($l) => $l !== ''));

            return $lines === [] ? null : $lines;
        }

        return null;
    }
}
