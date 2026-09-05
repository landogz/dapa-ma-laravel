<?php

namespace App\Repositories;

use App\Models\Training;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TrainingRepository
{
    public function paginate(
        int $perPage = 20,
        ?string $region = null,
        ?string $search = null,
        ?string $category = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        return Training::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($region, fn ($q) => $q->where('region', $region))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('venue', 'like', "%{$search}%")
                    ->orWhere('organizer', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            }))
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Training
    {
        return Training::findOrFail($id);
    }

    public function create(array $data): Training
    {
        return Training::create($data);
    }

    public function update(Training $training, array $data): Training
    {
        $training->update($data);

        return $training->fresh();
    }

    public function delete(Training $training): void
    {
        $training->delete();
    }
}
