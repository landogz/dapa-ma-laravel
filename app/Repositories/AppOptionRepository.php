<?php

namespace App\Repositories;

use App\Models\AppOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AppOptionRepository
{
    public function paginate(
        int $perPage = 100,
        ?string $search = null,
        ?string $group = null,
    ): LengthAwarePaginator {
        return AppOption::query()
            ->when($group, fn ($q) => $q->where('group', $group))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('label_en', 'like', "%{$search}%")
                        ->orWhere('label_tl', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%");
                });
            })
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function active(?string $group = null): Collection
    {
        return AppOption::query()
            ->where('is_active', true)
            ->when($group, fn ($q) => $q->where('group', $group))
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findOrFail(int $id): AppOption
    {
        return AppOption::query()->findOrFail($id);
    }

    public function create(array $data): AppOption
    {
        return AppOption::query()->create($data);
    }

    public function update(AppOption $option, array $data): AppOption
    {
        $option->update($data);

        return $option->fresh();
    }

    public function delete(AppOption $option): void
    {
        $option->delete();
    }

    public function nextSortOrder(string $group): int
    {
        $max = AppOption::query()
            ->where('group', $group)
            ->max('sort_order');

        return ((int) $max) + 1;
    }

    public function keyExists(string $group, string $key, ?int $ignoreId = null): bool
    {
        return AppOption::query()
            ->where('group', $group)
            ->where('key', $key)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function hasAny(?string $group = null): bool
    {
        return AppOption::query()
            ->when($group, fn ($q) => $q->where('group', $group))
            ->exists();
    }
}
