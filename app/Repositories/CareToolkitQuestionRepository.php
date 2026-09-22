<?php

namespace App\Repositories;

use App\Models\CareToolkitQuestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CareToolkitQuestionRepository
{
    public function paginate(
        int $perPage = 50,
        ?string $search = null,
        ?string $toolkitType = null,
    ): LengthAwarePaginator {
        return CareToolkitQuestion::query()
            ->when($toolkitType, fn ($q) => $q->where('toolkit_type', $toolkitType))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('question_en', 'like', "%{$search}%")
                        ->orWhere('question_tl', 'like', "%{$search}%");
                });
            })
            ->orderBy('toolkit_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function activeByType(string $toolkitType): Collection
    {
        return CareToolkitQuestion::query()
            ->where('toolkit_type', $toolkitType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findOrFail(int $id): CareToolkitQuestion
    {
        return CareToolkitQuestion::query()->findOrFail($id);
    }

    public function create(array $data): CareToolkitQuestion
    {
        return CareToolkitQuestion::query()->create($data);
    }

    public function update(CareToolkitQuestion $question, array $data): CareToolkitQuestion
    {
        $question->update($data);

        return $question->fresh();
    }

    public function delete(CareToolkitQuestion $question): void
    {
        $question->delete();
    }

    public function nextSortOrder(string $toolkitType): int
    {
        $max = CareToolkitQuestion::query()
            ->where('toolkit_type', $toolkitType)
            ->max('sort_order');

        return ((int) $max) + 1;
    }
}
