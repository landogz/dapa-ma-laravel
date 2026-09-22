<?php

namespace App\Repositories;

use App\Models\CareSupportResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CareSupportResourceRepository
{
    public function paginate(
        int $perPage = 50,
        ?string $search = null,
        ?string $category = null,
    ): LengthAwarePaginator {
        return CareSupportResource::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title_en', 'like', "%{$search}%")
                        ->orWhere('title_tl', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('description_en', 'like', "%{$search}%");
                });
            })
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function activeByCategory(string $category): Collection
    {
        return CareSupportResource::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findOrFail(int $id): CareSupportResource
    {
        return CareSupportResource::query()->findOrFail($id);
    }

    public function create(array $data): CareSupportResource
    {
        return CareSupportResource::query()->create($data);
    }

    public function update(CareSupportResource $resource, array $data): CareSupportResource
    {
        $resource->update($data);

        return $resource->fresh();
    }

    public function delete(CareSupportResource $resource): void
    {
        $resource->delete();
    }

    public function nextSortOrder(string $category): int
    {
        $max = CareSupportResource::query()
            ->where('category', $category)
            ->max('sort_order');

        return ((int) $max) + 1;
    }
}
