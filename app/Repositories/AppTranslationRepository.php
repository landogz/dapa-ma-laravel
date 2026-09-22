<?php

namespace App\Repositories;

use App\Models\AppTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AppTranslationRepository
{
    public function paginate(
        int $perPage = 50,
        ?string $search = null,
        ?string $group = null,
        ?bool $activeOnly = null,
    ): LengthAwarePaginator {
        return AppTranslation::query()
            ->when($activeOnly === true, fn ($q) => $q->where('is_active', true))
            ->when($group, fn ($q) => $q->where('group', $group))
            ->when($search, function ($q) use ($search): void {
                $q->where(function ($q) use ($search): void {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('group', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('value_en', 'like', "%{$search}%")
                        ->orWhere('value_tl', 'like', "%{$search}%");
                });
            })
            ->orderBy('group')
            ->orderBy('key')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): AppTranslation
    {
        return AppTranslation::query()->findOrFail($id);
    }

    public function create(array $data): AppTranslation
    {
        return AppTranslation::create($data);
    }

    public function update(AppTranslation $translation, array $data): AppTranslation
    {
        $translation->update($data);

        return $translation->fresh();
    }

    public function delete(AppTranslation $translation): void
    {
        $translation->delete();
    }

    public function groups(): Collection
    {
        return AppTranslation::query()
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');
    }

    public function activeBundle(?string $locale = null): Collection
    {
        return AppTranslation::query()
            ->where('is_active', true)
            ->orderBy('key')
            ->get(['key', 'value_en', 'value_tl', 'updated_at']);
    }

    public function latestUpdatedAt(): ?string
    {
        $value = AppTranslation::query()->where('is_active', true)->max('updated_at');

        return $value ? (string) $value : null;
    }

    public function upsertMany(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            AppTranslation::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'group' => $row['group'] ?? null,
                    'description' => $row['description'] ?? null,
                    'value_en' => $row['value_en'],
                    'value_tl' => $row['value_tl'],
                    'is_active' => $row['is_active'] ?? true,
                ],
            );
            $count++;
        }

        return $count;
    }
}
